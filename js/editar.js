

form = document.getElementById('formEditar');

inputNome = document.getElementById('editarNome');

statusNome = document.getElementById('statusNome');


inputNome.onblur = () => {
    if(inputNome.value == '') {
        statusNome.innerHTML = 'Preencha o nome';
    } else {
        statusNome.innerHTML = '';
    }
}

function enviarEditar() {
    if(
        inputNome.value == ''
    ) {
        form.style.background = 'rgb(192, 210, 243)';

        setTimeout(() => {
            form.removeAttribute('style');
            
            setTimeout(() => {
                form.style.background = 'rgb(192, 210, 243)';

                setTimeout(() => {
                    form.removeAttribute('style');
                }, 100);
            }, 50);
        }, 100);

        inputNome.onblur();

        return false;
    }

    return true;
}

function mostrarSenha() {
    inputSenha.type = inputSenha.type == 'text' ? 'password' : 'text';
}