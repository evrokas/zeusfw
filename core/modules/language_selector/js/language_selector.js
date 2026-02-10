// Initialize language selector based on mode
document.addEventListener('DOMContentLoaded', function() {
    const languageSelector = document.querySelector('.language-selector');

    if (!languageSelector) {
        return; // No language selector on this page
    }

    // Get the mode from data attribute
    const mode = languageSelector.getAttribute('data-mode') || 'ajax';

    // console.log('Language selector mode:', mode);

    if (mode === 'ajax') {
        // AJAX mode: Attach click handlers to flags
        initAjaxMode();
    } else if (mode === 'query_param') {
        // Query parameter mode: Links are already set up in template
        // No additional JavaScript needed, but we can add optional enhancements
        initQueryParamMode();
    }
});

/**
 * Initialize AJAX mode - existing behavior with fetch and page reload
 */
function initAjaxMode() {
    const flags = document.querySelectorAll('.language-selector img.flag');

    // console.log('AJAX mode: flags found:', flags.length);

    flags.forEach((el) => {
        el.addEventListener('click', (ev) => {
            // console.log('clicked:', ev.target.alt);

            fetch('language_select', {
                method: "POST",
                body: JSON.stringify({
                    language: ev.target.alt
                }),
                headers: {"Content-type": "application/json; charset=UTF-8"}
            })
            .then(response => response.json())
            .then(data => {
                console.log("Language switched:", data);
                location.reload();
            })
            .catch(err => {
                console.error("Error changing language:", err);
            });
        });
    });
}

/**
 * Initialize query parameter mode - optional enhancements
 */
function initQueryParamMode() {
    const links = document.querySelectorAll('.language-selector a.language-link');

    // console.log('Query param mode: links found:', links.length);

    // Optional: Add visual feedback on click
    links.forEach((link) => {
        link.addEventListener('click', (ev) => {
            // Optional: Show loading indicator
            const lang = ev.currentTarget.getAttribute('data-lang');
            console.log('Switching to language:', lang);

            // You could add a loading spinner here if desired
            // document.body.classList.add('language-switching');
        });
    });
}