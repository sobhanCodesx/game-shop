import NewsletterSignup from "../../NewsletterSignup";
import { buildNexusFocusModel } from "./nexusFocusData";
import NexusExplore from "./components/NexusExplore";
import NexusGames from "./components/NexusGames";
import NexusMedia from "./components/NexusMedia";
import NexusPulse from "./components/NexusPulse";
import NexusQuickPaths from "./components/NexusQuickPaths";
import NexusRadar from "./components/NexusRadar";
import NexusLatestSlider from "./components/NexusLatestSlider";
import NexusStore from "./components/NexusStore";
import type { NexusFocusInput } from "./types";

export default function NexusFocusHome(props: NexusFocusInput) {
    const model = buildNexusFocusModel(props);
    const personalized = Boolean(
        props.personalizedHome &&
        (props.personalizedHome.followed_games.length > 0 ||
            props.personalizedHome.events.length > 0 ||
            props.personalizedHome.feed.length > 0 ||
            props.personalizedHome.videos.length > 0),
    );

    return (
        <div className="pb-5 sm:pb-8">
            <h1 className="sr-only">{props.heading}</h1>
            <NexusLatestSlider items={model.latestSlides} />
            <NexusQuickPaths />
            <NexusPulse items={model.pulse} personalized={personalized} />
            <NexusGames items={model.games} />
            <NexusMedia
                featuredVideo={model.featuredVideo}
                stories={model.stories}
            />
            <NexusStore products={model.products} />
            <NexusRadar signals={model.radar} />
            <NexusExplore items={model.explore} />
            {props.newsletter.enabled && (
                <NewsletterSignup
                    description={props.newsletter.description}
                    title={props.newsletter.title}
                />
            )}
        </div>
    );
}
