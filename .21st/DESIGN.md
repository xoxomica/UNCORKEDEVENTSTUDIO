# Uncorked design context

The selected user-supplied single-file page is the visual baseline. Preserve its editorial serif typography, warm ivory background, wine accents, real bar photographs, wedding portfolio and existing booking behavior.

The primary product is the pop-up bar. Animation should tell its story through spatial movement rather than repeated fade-ins. The opening keeps a sticky stage while native scrolling drives camera scale, image wipes and copy movement. The original horizontal services sequence remains the second pinned sequence.

Reference inspected: https://www.endurosat.com/space-service/

21st inspiration retrieved: soralabs, Scroll Gallery, demo 30118 — https://21st.dev/@soralabs/components/scroll-gallery. Its sticky image-stack, scale and wipe concept is adapted to the existing PHP/vanilla stack; React and GSAP were not added.

The 21st CLI is unavailable in this workspace; catalog search/retrieval used the connected 21st MCP. Context is recorded here from the inspected project.

Keep chapter controls keyboard accessible. Remove hidden original hero controls from tab order during later scenes. Preserve normal compact-viewport rendering, prefers-reduced-motion, and Pause motion. Never intercept wheel/touch events or replace native document scrolling.

Hero captions use solid ivory (#fffaf3) and champagne (#ffe2b2), with the inherited wine text shimmer disabled for these captions. Directional dark overlays follow the left/right copy position. Use overflow:clip on the sticky hero to prevent focus from internally scrolling the image stage. Scrub smoothing uses elapsed time so it does not depend on frame rate.
