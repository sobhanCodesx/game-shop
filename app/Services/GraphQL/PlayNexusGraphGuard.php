<?php

namespace App\Services\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use RuntimeException;

class PlayNexusGraphGuard
{
    /**
     * @return array{document:DocumentNode,hash:string,depth:int,complexity:int,fields:int,introspection:bool}
     */
    public function analyze(string $query, array $variables = []): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new RuntimeException('GraphQL query is required.');
        }

        $maxBytes = (int) config('content_agent.graphql.max_query_bytes', 24000);
        if (strlen($query) > $maxBytes) {
            throw new RuntimeException("GraphQL query is too large. Maximum is {$maxBytes} bytes.");
        }

        $document = Parser::parse($query);
        $fragments = [];
        $operations = [];

        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[$definition->name->value] = $definition;
            }

            if ($definition instanceof OperationDefinitionNode) {
                $operations[] = $definition;
            }
        }

        if ($operations === []) {
            throw new RuntimeException('GraphQL document must contain a query operation.');
        }

        foreach ($operations as $operation) {
            if ((string) $operation->operation !== 'query') {
                throw new RuntimeException('PlayNexus Graph is read-only. Mutations and subscriptions are not allowed.');
            }
        }

        $metrics = ['depth' => 0, 'fields' => 0, 'complexity' => 0, 'introspection' => false];

        foreach ($operations as $operation) {
            $this->walkSelectionSet(
                $operation->selectionSet->selections,
                $fragments,
                $variables,
                1,
                $metrics,
                [],
            );
        }

        $maxFields = (int) config('content_agent.graphql.max_fields', 250);
        if ($metrics['fields'] > $maxFields) {
            throw new RuntimeException("GraphQL query selects too many fields ({$metrics['fields']}/{$maxFields}).");
        }

        if ($metrics['introspection'] && ! (bool) config('content_agent.graphql.allow_introspection', true)) {
            throw new RuntimeException('GraphQL introspection is disabled.');
        }

        $maxDepth = (int) config(
            $metrics['introspection']
                ? 'content_agent.graphql.max_introspection_depth'
                : 'content_agent.graphql.max_depth',
            $metrics['introspection'] ? 16 : 10,
        );

        if ($metrics['depth'] > $maxDepth) {
            throw new RuntimeException("GraphQL query is too deep ({$metrics['depth']}/{$maxDepth}).");
        }

        $maxComplexity = (int) config('content_agent.graphql.max_complexity', 500);
        if ($metrics['complexity'] > $maxComplexity) {
            throw new RuntimeException("GraphQL query is too complex ({$metrics['complexity']}/{$maxComplexity}).");
        }

        return [
            'document' => $document,
            'hash' => hash('sha256', $query),
            'depth' => $metrics['depth'],
            'complexity' => $metrics['complexity'],
            'fields' => $metrics['fields'],
            'introspection' => $metrics['introspection'],
        ];
    }

    private function walkSelectionSet(
        iterable $selections,
        array $fragments,
        array $variables,
        int $depth,
        array &$metrics,
        array $fragmentStack,
    ): int {
        $metrics['depth'] = max($metrics['depth'], $depth);
        $cost = 0;

        foreach ($selections as $selection) {
            if ($selection instanceof FieldNode) {
                $name = $selection->name->value;
                $metrics['fields']++;

                if ($name === '__schema' || $name === '__type') {
                    $metrics['introspection'] = true;
                }

                $childCost = 0;
                if ($selection->selectionSet !== null) {
                    $childCost = $this->walkSelectionSet(
                        $selection->selectionSet->selections,
                        $fragments,
                        $variables,
                        $depth + 1,
                        $metrics,
                        $fragmentStack,
                    );
                }

                $multiplier = $this->fieldMultiplier($selection, $variables);
                $cost += 1 + ($childCost * $multiplier);

                continue;
            }

            if ($selection instanceof InlineFragmentNode) {
                $cost += $this->walkSelectionSet(
                    $selection->selectionSet->selections,
                    $fragments,
                    $variables,
                    $depth + 1,
                    $metrics,
                    $fragmentStack,
                );

                continue;
            }

            if ($selection instanceof FragmentSpreadNode) {
                $name = $selection->name->value;

                if (in_array($name, $fragmentStack, true)) {
                    throw new RuntimeException('Recursive GraphQL fragments are not allowed.');
                }

                $fragment = $fragments[$name] ?? null;
                if ($fragment instanceof FragmentDefinitionNode) {
                    $cost += $this->walkSelectionSet(
                        $fragment->selectionSet->selections,
                        $fragments,
                        $variables,
                        $depth + 1,
                        $metrics,
                        [...$fragmentStack, $name],
                    );
                }
            }
        }

        $metrics['complexity'] += $cost;

        return $cost;
    }

    private function fieldMultiplier(FieldNode $field, array $variables): int
    {
        $name = $field->name->value;
        $first = null;

        foreach ($field->arguments as $argument) {
            if (! in_array($argument->name->value, ['first', 'limit'], true)) {
                continue;
            }

            $valueNode = $argument->value;
            $class = class_basename($valueNode);

            if ($class === 'IntValueNode') {
                $first = (int) $valueNode->value;
            } elseif ($class === 'VariableNode') {
                $first = (int) ($variables[$valueNode->name->value] ?? 0);
            }
        }

        $max = (int) config('content_agent.graphql.max_page_size', 50);
        $connectionFields = [
            'games', 'studios', 'platforms', 'products', 'contents', 'feeds',
            'videos', 'stories', 'collections', 'categories', 'radar', 'content', 'children',
            'gameEvents', 'sourceStates', 'events',
        ];

        if ($first === null || $first < 1) {
            $first = $name === 'search'
                ? 8
                : (in_array($name, $connectionFields, true) ? 20 : 1);
        }

        $first = max(1, min($max, $first));

        if ($name === 'search') {
            return min($max, $first * 5);
        }

        return $first;
    }
}
