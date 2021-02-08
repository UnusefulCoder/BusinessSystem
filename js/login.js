

function blur(inputID, statusID, string) {
    var input = document.getElementById(inputID);
    var status = document.getElementById(statusID);

    if(input != null) {
        input.onfocus = () => status.innerHTML = '';
        input.onblur = () => status.innerHTML = input.value == '' ? string : '';
    }
}

blur('usuario', 'statusUsuario', 'Preencha o nome');
blur('senha', 'statusSenha', 'Preencha a senha');

function enviar(form, inputs) {
    if(inputs.some(input => document.getElementById(input).value == '')) {
        form = document.getElementById(form);

        form.classList.add('blink');
        
        setTimeout(() => form.removeAttribute("class"), 400);

        inputs.forEach(input => document.getElementById(input).onblur());

        return false;
    }

    return true;
}

function showSnackbar(message) {
    var snackbar = document.getElementById("snackbar");

    snackbar.children[0].innerText = message;
    snackbar.className = "show";

    setTimeout(() => closeSnackbar(), 15000);
}

function closeSnackbar() {
    var snackbar = document.getElementById("snackbar");
    
    snackbar.removeAttribute("class");
    snackbar.children[0].innerText = "";
}