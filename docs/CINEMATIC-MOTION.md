# Cinematic motion update

Implemented in the root index.php, retaining inline PHP/HTML/CSS/JavaScript and the existing public/index.php wrapper.

- Opening: native scroll through a stationary hero; photo push, opposing wipes, three caption scenes, chapter navigation and skip link.
- Existing One team horizontal scroll and two Moments photo strips retained.
- Desktop enhancement requires a viewport at least 900px wide and 650px high. Compact viewports keep the original readable hero and inquiry form.
- Pause motion removes pinning and restores original controls. Reduced-motion preferences skip the enhancement.
- No wheel interception, full-page transform scroller, new JavaScript dependency, backend changes, or database package changes.

Visual direction: EnduroSat Space Service, inspected in the browser; 21st soralabs Scroll Gallery demo 30118, adapted as a native sticky image stack. Uncorked's existing real images, warm palette and serif type remain.

Verification on 21 October 2026: PHP lint and extracted inline JavaScript syntax passed. Browser checks covered all three chapter positions, reverse navigation, native PageDown with the stage remaining at the 76px header offset, full image-stage coverage, Pause motion cleanup, and a 390px mobile layout without horizontal overflow. Reduced-motion CSS/media handling was reviewed, not emulated in the browser. The 21st CLI is unavailable; catalog search/retrieval used MCP.

Preview images: cinematic-bar.png, cinematic-pours.png, cinematic-details.png in this folder. The pre-change source is preserved privately at storage/index-before-cinematic-scroll.php.
