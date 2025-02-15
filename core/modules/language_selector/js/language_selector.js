const flags = document.querySelectorAll('.language-selector img.flag');

// console.log('flags: ', flags);

flags.forEach((el) => {
    el.addEventListener('click', (ev) => {
        // console.log('clicked: ', ev, ev.target.alt);

        fetch( 'language_select', {
            method: "POST",
            body: JSON.stringify({
                language: ev.target.alt
            }),
            headers: {"Content-type" : "application/json; charset=UTF-8"}
        })
        .then(response => response.json())
        .then(data => {
                console.log("response: ", data);
                location.reload();
        })
        .catch(err => {
            console.log("error changing language: ", err)
        });
    })
});