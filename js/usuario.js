

function tabelaPagina(pagina) {
    var input = document.createElement('input');

    input.setAttribute('name', 'tabelaPagina');
    input.setAttribute('value', pagina);

    var form = document.createElement('form');

    form.style.display = 'none';
    form.setAttribute('action', '?pagina=usuarios');
    form.setAttribute('method', 'POST');

    form.appendChild(input);

    document.body.appendChild(form);

    form.submit();
}

function mostrarSenha(nome) {
    var input = document.getElementById(nome);

    input.type = input.type == 'text' ? 'password' : 'text';
}

function enviarCriar() {
    return enviar(document.getElementById('formCriar'), [
        document.getElementById('criarUsuario'),
        document.getElementById('criarNome'),
        document.getElementById('criarSenha')
    ]);
}

function enviarEditar() {
    return enviar(document.getElementById('formEditar'), [
        document.getElementById('editarNome')
    ]);
}

function blur(inputID, statusID, string) {
    var input = document.getElementById(inputID);
    var status = document.getElementById(statusID);

    if(input != null) {
        input.onfocus = () => status.innerHTML = '';
        input.onblur = () => status.innerHTML = input.value == '' ? string : '';
    }
}

blur('criarUsuario', 'statusUsuario', 'Preencha o usuário');
blur('criarNome', 'statusNome', 'Preencha o nome');
blur('criarSenha', 'statusSenha', 'Preencha a senha');

blur('editarNome', 'statusNome', 'Preencha o nome');
blur('editarSenha', 'statusSenha', '');

function enviar(form, inputs) {
    if(inputs.some(input => input.value == '')) {
        form.classList.add('blink');
        
        setTimeout(() => form.removeAttribute("class"), 400);

        inputs.forEach(input => input.onblur());

        return false;
    }

    return true;
}

function remover(element) {
    if(confirm('Tem certeza que deseja excluir o usuário ' + element.children[1].innerHTML + '?\n')) {
        var input = document.createElement('input');

        input.setAttribute('name', 'removerID');
        input.setAttribute('value', element.children[0].innerHTML);
    
        var form = document.createElement('form');
    
        form.style.display = 'none';
        form.setAttribute('action', '?pagina=usuarios_remover');
        form.setAttribute('method', 'POST');
    
        form.appendChild(input);
    
        document.body.appendChild(form);
    
        form.submit();
    }
}

function showSnackbar(message) {
    var snackbar = document.getElementById("snackbar");

    snackbar.innerText = message;
    snackbar.className = "show";

    setTimeout(() => {
        snackbar.removeAttribute("class");
        snackbar.innerText = "";
    }, 3000);
}