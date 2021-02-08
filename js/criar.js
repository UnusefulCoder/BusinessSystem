

form = document.getElementById('formCriar');

inputUsuario = document.getElementById('criarUsuario');
inputNome = document.getElementById('criarNome');
inputSenha = document.getElementById('criarSenha');

statusUsuario = document.getElementById('statusUsuario');
statusNome = document.getElementById('statusNome');
statusSenha = document.getElementById('statusSenha');

function blur(string) {
    if(inputUsuario.value == '') {
        statusUsuario.innerHTML = 'Preencha ' + string;
    } else {
        statusUsuario.innerHTML = '';
    }
}

inputUsuario.onblur = blur('o usuário');
inputNome.onblur = blur('o nome');
inputSenha.onblur = blur('a senha');

function enviarCriar(form, inputs) {
    console.log(inputs.every((value, index, array) => {
        return value.value;
    }, inputs));

    if(inputs.contains('')) {
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

        inputUsuario.onblur();
        inputNome.onblur();
        inputSenha.onblur();

        return false;
    }

    return true;
}

function mostrarSenha() {
    inputSenha.type = inputSenha.type == 'text' ? 'password' : 'text';
}