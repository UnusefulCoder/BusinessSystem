

function remover(element) {
    if(confirm('Tem certeza que deseja excluir o usuário ' + element.children[1].innerHTML + '?\n')) {
        var input = document.createElement('input');

        input.setAttribute('name', 'removerID');
        input.setAttribute('value', element.children[0].innerHTML);
    
        var form = document.createElement('form');
    
        form.style.display = 'none';
        form.setAttribute('action', 'index.php?pagina=gerenciar');
        form.setAttribute('method', 'POST');
    
        form.appendChild(input);
    
        document.body.appendChild(form);
    
        form.submit();
    }
}

function editar(element) {
    var parent = element.parentNode;

    document.getElementById("editarID").setAttribute("value", parent.children[0].innerHTML);
    document.getElementById("editarUsuario").setAttribute("value", parent.children[1].innerHTML);
    document.getElementById("editarNome").setAttribute("value", parent.children[2].innerHTML);
    document.getElementById("editarPrivilegio").setAttribute("value", parent.children[3].innerHTML);

    document.getElementById("formEditar").className = "show";
}

function tabelaPagina(pagina) {
    var input = document.createElement('input');

    input.setAttribute('name', 'tabelaPagina');
    input.setAttribute('value', pagina);

    var form = document.createElement('form');

    form.style.display = 'none';
    form.setAttribute('action', 'gerenciar.php');
    form.setAttribute('method', 'POST');

    form.appendChild(input);

    document.body.appendChild(form);

    form.submit();
}

function mostrarSenha(id) {
    senha = document.getElementById(id);

    senha.type = senha.type == 'text' ? 'password' : 'text';
}