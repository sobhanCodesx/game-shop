from pathlib import Path
import textwrap

source = Path(".github/workflows/one-shot-content-relations-v7.yml").read_text()
start = source.index("          python3 <<'PY'\n") + len("          python3 <<'PY'\n")
end = source.index("\n          PY", start)
outer = textwrap.dedent(source[start:end])
final_exec = "exec(compile(script, '<content-relations-patch>', 'exec'))"
if outer.count(final_exec) != 1:
    raise SystemExit("v7 final exec marker missing")

structural_code = """schema_relation_start = text.index("                'relatedProduct' => [")
schema_relation_end = text.index("                'relatedContent' => [", schema_relation_start)
existing_relation = text[schema_relation_start:schema_relation_end]
digital_relation = "                'relatedDigitalProduct' => [\\n                    'type' => $this->digitalProductType(),\\n                    'resolve' => fn (SocialContent $content) => $content->relationLoaded('relatedDigitalProduct')\\n                        ? $content->relatedDigitalProduct\\n                        : $content->relatedDigitalProduct()->first(),\\n                ],\\n"
text = text[:schema_relation_start] + existing_relation + digital_relation + text[schema_relation_end:]
"""
graph_patch = (
    "rel_start = script.index('relation_marker =')\n"
    "rel_end = script.index('text = text.replace(\\n    \"            \\\'gameId\\\' => [\\\'type\\\' => Type::id()],', rel_start)\n"
    "structural = " + repr(structural_code) + "\n"
    "script = script[:rel_start] + structural + script[rel_end:]\n"
    "exec(compile(script, '<content-relations-patch>', 'exec'))"
)
outer = outer.replace(final_exec, graph_patch, 1)
exec(compile(outer, "<v7-structural-graph>", "exec"))
