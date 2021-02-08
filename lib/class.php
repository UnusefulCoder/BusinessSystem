<?php

require_once('lib/raelgc/view/Template.php');

abstract class Conexao
{
    public mysqli $conexao;
    public mysqli_result | bool $resultado;

    public function escape(string $value): string
    {
        if (!isset($this->conexao)) $this->conectar();

        return "'" . $this->conexao->escape_string($value) . "'";
    }

    protected function query($query): int
    {
        if (!isset($this->conexao)) $this->conectar();

        $this->resultado = $this->conexao->query($query);

        return $this->conexao->affected_rows;
    }

    public function validar($tipo, $valor, $min = false, $max = false)
    {
        if ($tipo == 'usuario') $pattern = '/[a-zA-ZáàãâäéèêëíìîïóòôöúùûüñýÿçÁÀÃÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÑÇ]{1,30}/';
        if ($tipo == 'nome') $pattern = '/[a-zA-ZáàãâäéèêëíìîïóòôöúùûüñýÿçÁÀÃÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÑÇ ]{1,100}/';
        if ($tipo == 'senha') $pattern = '/[a-zA-Z0-9!@#$%&*,.:;-_+=?]{8,}/';
        if ($tipo == 'inteiro') {
            if ($min && $max) {
                if (intval($valor) >= $min && intval($valor) <= $max) $pattern = '/[[:digit:]]/';
            } else $pattern = '/[[:digit:]]/';
        }

        if (!isset($pattern) || !preg_match($pattern, $valor)) throw new InvalidArgumentException('Dados inválidos!');

        return $this->escape($valor);
    }

    public function validar0($tipo, $valor, $min = false, $max = false)
    {
        if (strlen($valor) == 0) return 1;

        if ($tipo == 'alpha' && !ctype_alpha($valor)) return 2;
        if ($tipo == 'print' && !ctype_print($valor)) return 3;
        if ($tipo == 'digit' && !ctype_digit($valor)) return 4;
        if ($tipo == 'digit' && is_int($max) && is_int($min) && (intval($valor) < $min || intval($valor > $max))) return 5;

        return 0;
    }

    private function conectar(): void
    {
        $this->conexao = new mysqli("localhost", "sistema", "sistema123", "sistema");

        if ($this->conexao->connect_errno) throw new mysqli_sql_exception("Falha na conexão com o banco de dados!");
    }
}

function mostrarHome()
{
    $tpl = new \raelgc\view\Template('home.html');

    $tpl->NOME = $_SESSION['nome'];
    $tpl->IP = $_SERVER['REMOTE_ADDR'] == '::1' ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];

    $tpl->ELEMENTO = "<script defer>document.getElementById('home').classList.add('active')</script>";

    $tpl->addFile('TOPO', 'topo.html');

    if (isset($_SESSION['status'])) {
        $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $_SESSION["status"] . '"), 1);</script>';

        unset($_SESSION['status']);
    }

    $tpl->show();
}

class Usuarios extends Conexao
{
    function mostrar()
    {
        if (!isset($_GET['pg']) || $_GET['pg'] < 1) $pagina = 1;
        else $pagina = $_GET['pg'];

        $usuariosQuant = $this->queryUsuario('COUNT');
        $maxPagina = (int)ceil($usuariosQuant / 10);

        if ($pagina > $maxPagina) $pagina = $maxPagina;

        $tpl = new \raelgc\view\Template('usuarios.html');
        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários';
        $tpl->ELEMENTO = "<script defer>document.getElementById('gerenciar').classList.add('active');document.getElementById('usuarios-mostrar').classList.add('active');</script>";

        $tpl->USUARIOS = $usuariosQuant;
        $tpl->MAXPAGINA = $maxPagina;
        $tpl->PAGINA = $pagina;

        if ($pagina == 1) {
            $tpl->DES_1 = 'disabled';
            if ($maxPagina == 1) $tpl->DES_2 = 'disabled';
        }
        if ($pagina == $maxPagina) $tpl->DES_3 = 'disabled';

        if ($this->queryUsuario('SELECT', false, false, ($pagina - 1) * 10, 10)) {
            foreach ($this->resultado->fetch_all() as $usuario) {
                $tpl->ID = $usuario[0];
                $tpl->NOME = $usuario[2];
                $tpl->USUARIO = $usuario[1];
                $tpl->PRIVILEGIO = $usuario[3];
                $tpl->block('TABELA_LINHA');
            }
        }

        $tpl->block('TABELA');

        if (isset($_SESSION['status'])) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $_SESSION["status"] . '"), 1);</script>';

            unset($_SESSION['status']);
        }

        $tpl->show();
    }

    function incluir()
    {
        $tpl = new \raelgc\view\Template('usuarios.html');

        try {
            if (
                isset($_POST['criarUsuario']) &&
                isset($_POST['criarNome']) &&
                isset($_POST['criarPrivilegio']) &&
                isset($_POST['criarSenha'])
            ) {
                if ($this->queryUsuario('SELECT', 'usuario=' . $this->escape($_POST['criarUsuario']))) throw new InvalidArgumentException('Nome de usuário indisponível!');

                if ($this->queryUsuario('INSERT', $this->validar('usuario', $_POST['criarUsuario']) . ', ' . $this->validar('nome', $_POST['criarNome']) . ', ' . $this->validar('inteiro', $_POST['criarPrivilegio'], 0, 3) . ', PASSWORD(' . $this->validar('senha', $_POST['criarSenha']) . ')')) {
                    $_SESSION['status'] = 'Sucesso!';
                    header('Location: ?pagina=usuarios');
                    exit;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->VALUE_USUARIO = 'value=' . $_POST['criarUsuario'];
            $tpl->VALUE_NOME = 'value="' . $_POST['criarNome'] . '"';
            $tpl->__set('PRIVILEGIO_' . $_POST['criarPrivilegio'], 'selected');
            $tpl->VALUE_SENHA = 'value="' . $_POST['criarSenha'] . '"';
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Novo Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-incluir').classList.add('active');</script>";

        $tpl->block('INCLUIR');
        $tpl->show();
    }

    function alterar()
    {
        if (!isset($_GET['id'])) {
            $_SESSION['status'] = 'Escolha o usuário para editar!';
            header('Location: ?pagina=usuarios');
            exit;
        }

        if (!$this->queryUsuario('SELECT', 'id=' . $this->escape($_GET['id']))) {
            $_SESSION['status'] = 'Usuário não encontrado!';
            header('Location: ?pagina=usuarios');
            exit;
        }

        $resultado = $this->resultado->fetch_assoc();

        $tpl = new \raelgc\view\Template('usuarios.html');

        $tpl->ID = $resultado['id'];
        $tpl->USUARIO = htmlspecialchars($resultado['usuario']);
        $tpl->NOME = $resultado['nome'];
        $tpl->__set('PRIVILEGIO_' . $resultado['privilegio'], 'selected');

        try {
            if (
                isset($_POST['editarID']) &&
                isset($_POST['editarUsuario']) &&
                isset($_POST['editarNome']) &&
                isset($_POST['editarPrivilegio']) &&
                isset($_POST['editarSenha'])
            ) {
                if (!$this->queryUsuario('SELECT', 'id=' . $this->escape($_POST['editarID']))) {
                    $_SESSION['status'] = 'Usuário não encontrado!';
                    header('Location: ?pagina=usuarios');
                    exit;
                }

                $resultado = $this->resultado->fetch_assoc();

                $query = '';
                $query .= $resultado['nome'] != $_POST['editarNome'] ? 'nome=' . $this->validar('nome', $_POST['editarNome']) . ', ' : '';
                $query .= $resultado['privilegio'] != $_POST['editarPrivilegio'] ? 'privilegio=' . $this->validar('inteiro', $_POST['editarPrivilegio'], 0, 3) . ', ' : '';

                if (!strlen($_POST['editarSenha']) == 0) $query .= 'senha=PASSWORD(' . $this->validar('senha', $_POST['editarSenha']) . '), ';

                if (strlen($query) == 0) throw new InvalidArgumentException('Nada para editar!');

                if ($this->queryUsuario('UPDATE', 'id=' . $this->escape($_POST['editarID']), substr($query, 0, -2))) {
                    $_SESSION['status'] = 'Sucesso!';
                    header('Location: ?pagina=usuarios');
                    exit;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->NOME = $_POST['editarNome'];
            $tpl->__set('PRIVILEGIO_' . $_POST['editarPrivilegio'], 'selected');
            $tpl->VALUE_SENHA = 'value=' . $_POST['editarSenha'];
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Editar Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-alterar').classList.add('active');</script>";

        $tpl->block('ALTERAR');
        $tpl->show();
    }

    function remover()
    {
        if (isset($_GET['id'])) {
            if ($this->queryUsuario('SELECT', 'id=' . $this->escape($_GET['id'])) == 0) $_SESSION['status'] = 'Usuário não encontrado!';
            else $_SESSION['status'] = $this->queryUsuario('DELETE', 'id=' . $this->escape($_GET['id'])) ? 'Sucesso!' : 'Falha!';
        } else {
            $_SESSION['status'] = 'Escolha o usuário para excluir!';
        }

        header('Location: ?pagina=usuarios');
        exit;
    }

    function pesquisar()
    {
        if (!isset($_POST['pesquisa']) || $_POST['pesquisa'] == '') {
            header('Location: ?pagina=usuarios');
            exit;
        }

        $string = $_POST['pesquisa'];

        if (intval($string)) $resultado = $this->queryUsuario('SELECT', 'id=' . $this->escape($string));
        else $resultado = $this->queryUsuario('SELECT', 'usuario LIKE "%' . $string . '%" OR nome LIKE "%' . $string . '%"');

        $tpl = new \raelgc\view\Template('usuarios.html');

        $tpl->PAGINA = $tpl->MAXPAGINA = 1;
        $tpl->DES_1 = $tpl->DES_2 = $tpl->DES_3 = 'disabled';

        if (($tpl->USUARIOS = $resultado) > 0) {
            foreach ($this->resultado->fetch_all() as $usuario) {
                $tpl->ID = $usuario[0];
                $tpl->NOME = $usuario[2];
                $tpl->USUARIO = $usuario[1];
                $tpl->PRIVILEGIO = $usuario[3];
                $tpl->block('TABELA_LINHA');
            }
        }

        $tpl->block('TABELA');

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->show();
    }

    function queryUsuario($acao, $condicao = false, $campos = false, $comeco = false, $quantidade = false, $ordenacao = false)
    {
        if ($acao == 'SELECT') return $this->query('SELECT id, usuario, nome, privilegio FROM usuarios' . ($condicao ? ' WHERE ' . $condicao : '') . ($ordenacao ? ' ORDER BY ' . $ordenacao : '') . (is_int($comeco) && is_int($quantidade) ? ' LIMIT ' . $comeco . ',' . $quantidade : ''));
        if ($acao == 'INSERT' && $condicao) return $this->query('INSERT INTO usuarios (usuario, nome, privilegio, senha) VALUES (' . $condicao . ')');
        if ($acao == 'UPDATE' && $condicao && $campos) return $this->query('UPDATE usuarios SET ' . $campos . ' WHERE ' . $condicao);
        if ($acao == 'DELETE' && $condicao) return $this->query('DELETE FROM usuarios WHERE ' . $condicao);
        if ($acao == 'COUNT') {
            $this->query('SELECT COUNT(id) FROM usuarios');

            return $this->resultado->fetch_assoc()['COUNT(id)'];
        }

        return 0;
    }
}

class Logs extends Conexao
{
    function log($acao, $usuario, $status, $tabela = '', $coluna = '', $atual = '', $anterior = '')
    {
        if ($acao == 'LOGIN') return $this->query('INSERT', 'acao=' . $this->escape($acao) . ' usuario=' . $this->escape($usuario) . ' status=' . $this->escape($status), 'logs');
        if ($acao == 'INCLUIR' && $tabela != '' && $atual != '') return $this->query('INSERT', 'acao=' . $this->escape($acao) . ' usuario=' . $this->escape($usuario) . ' tabela=' . $this->escape($tabela) . ' atual=' . $this->escape($atual) . ' status=' . $this->escape($status), 'logs');
        if ($acao == 'ALTERAR' && $tabela != '' && $coluna != '' && $atual != '' && $anterior != '') return $this->query('INSERT', 'acao=' . $this->escape($acao) . ' usuario=' . $this->escape($usuario) . ' tabela=' . $this->escape($tabela) . ' coluna=' . $this->escape($coluna) . ' atual=' . $this->escape($atual) . ' anterior=' . $this->escape($anterior) . ' status=' . $this->escape($status), 'logs');
        if ($acao == 'REMOVER' && $tabela != '' && $anterior != '') return $this->query('INSERT', 'acao=' . $this->escape($acao) . ' usuario=' . $this->escape($usuario) . ' tabela=' . $this->escape($tabela) . ' anterior=' . $this->escape($anterior) . ' status=' . $this->escape($status), 'logs');
        if ($acao == 'LOGOUT') return false;

        return false;
    }
}
