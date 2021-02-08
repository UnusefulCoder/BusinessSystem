

function showSnackbar(message) {
    var snackbar = document.getElementById("snackbar");

    snackbar.innerText = message;
    snackbar.className = "show";

    setTimeout(() => {
        snackbar.removeAttribute("class");
        snackbar.innerText = "";
    }, 3000);
}