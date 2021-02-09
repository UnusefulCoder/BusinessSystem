<?php

include 'lib/Template.php';

final class Server
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_SERVER[$key]);
    }
    
    static function get($key)
    {
        return (isset($_SERVER[$key]) ? $_SERVER[$key] : null);
    }

    static function set($key, $value)
    {
        $_SERVER[$key] = $value;
    }

    static function forget($key)
    {
        unset($_SERVER[$key]);
    }
}
final class Session
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    static function get($key)
    {
        return (isset($_SESSION[$key]) ? $_SESSION[$key] : null);
    }

    static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    static function forget($key)
    {
        unset($_SESSION[$key]);
    }
}
final class Get
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_GET[$key]);
    }
    
    static function get($key)
    {
        return (isset($_GET[$key]) ? strip_tags($_GET[$key]) : null);
    }

    static function set($key, $value)
    {
        $_GET[$key] = $value;
    }

    static function forget($key)
    {
        unset($_GET[$key]);
    }
}
final class Post
{
    private function __construct() { }

    static function has($key)
    {
        return isset($_POST[$key]);
    }

    static function hasAll($keys)
    {
        foreach ($keys as $key) {
            if (!isset($_POST[$key])) return false;
        }

        return true;
    }
    
    static function get($key)
    {
        return (isset($_POST[$key]) ? strip_tags($_POST[$key]) : null);
    }

    static function set($key, $value)
    {
        $_POST[$key] = $value;
    }

    static function forget($key)
    {
        unset($_POST[$key]);
    }
}

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
                if ((int)$valor >= $min && (int)$valor <= $max) $pattern = '/[[:digit:]]/';
            } else $pattern = '/[[:digit:]]/';
        }

        if (!isset($pattern) || !preg_match($pattern, $valor)) throw new InvalidArgumentException('Dados inválidos!');

        return $this->escape($valor);
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

    $tpl->NOME = Session::get('nome');
    $tpl->IP = Server::get('REMOTE_ADDR') == '::1' ? '127.0.0.1' : Server::get('REMOTE_ADDR');

    $tpl->ELEMENTO = "<script defer>document.getElementById('home').classList.add('active')</script>";

    $tpl->addFile('TOPO', 'topo.html');

    if (Session::has('status')) {
        $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . Session::get('status') . '"), 1);</script>';

        Session::forget('status');
    }

    $tpl->show();
}

class Usuarios extends Conexao
{
    function mostrar()
    {
        if (Get::get('pg') < 1) $pagina = 1;
        else $pagina = Get::get('pg');

        $usuariosQuant = $this->queryUsuario('COUNT');
        $maxPagina = (int)ceil($usuariosQuant / 10);

        if ($pagina > $maxPagina) $pagina = $maxPagina;

        $tpl = new \raelgc\view\Template('usuarios.html');
        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários';
        $tpl->ELEMENTO = "<script>document.getElementById('gerenciar').classList.add('active'); document.getElementById('usuarios-mostrar').classList.add('active');</script>";

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

        if (Session::has('status')) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . Session::get('status') . '"), 1);</script>';
    
            Session::forget('status');
        }

        $tpl->show();
    }

    function incluir()
    {
        $tpl = new \raelgc\view\Template('usuarios.html');

        try {
            if (Post::hasAll(['criarUsuario', 'criarNome', 'criarPrivilegio', 'criarSenha'])) {
                if ($this->queryUsuario('SELECT', 'usuario=' . $this->escape(Post::get('criarUsuario')))) throw new InvalidArgumentException('Nome de usuário indisponível!');

                if ($this->queryUsuario('INSERT', $this->validar('usuario', Post::get('criarUsuario')) . ', ' . $this->validar('nome', Post::get('criarNome')) . ', ' . $this->validar('inteiro', Post::get('criarPrivilegio'), 0, 3) . ', PASSWORD(' . $this->validar('senha', Post::get('criarSenha')) . ')')) {
                    Session::set('status', 'Sucesso!');
                    header('Location: ?pagina=usuarios');
                    return;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->VALUE_USUARIO = 'value=' . Post::get('criarUsuario');
            $tpl->VALUE_NOME = 'value="' . Post::get('criarNome') . '"';
            $tpl->__set('PRIVILEGIO_' . Post::get('criarPrivilegio'), 'selected');
            $tpl->VALUE_SENHA = 'value="' . Post::get('criarSenha') . '"';
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Novo Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-incluir').classList.add('active');</script>";

        $tpl->block('INCLUIR');
        $tpl->show();
    }

    function alterar()
    {
        if (!Get::has('id')) {
            Session::set('status', 'Escolha o usuário para editar!');
            header('Location: ?pagina=usuarios');
            return;
        }

        if (!$this->queryUsuario('SELECT', 'id=' . $this->escape(Get::get('id')))) {
            Session::set('status', 'Usuário não encontrado!');
            header('Location: ?pagina=usuarios');
            return;
        }

        $resultado = $this->resultado->fetch_assoc();

        $tpl = new \raelgc\view\Template('usuarios.html');

        $tpl->ID = $resultado['id'];
        $tpl->USUARIO = htmlspecialchars($resultado['usuario']);
        $tpl->NOME = $resultado['nome'];
        $tpl->__set('PRIVILEGIO_' . $resultado['privilegio'], 'selected');

        try {
            if (Post::hasAll(['editarID', 'editarUsuario', 'editarNome', 'editarPrivilegio', 'editarSenha'])) {
                if (!$this->queryUsuario('SELECT', 'id=' . $this->escape(Post::get('editarID')))) {
                    Session::set('status', 'Usuário não encontrado!');
                    header('Location: ?pagina=usuarios');
                    return;
                }

                $resultado = $this->resultado->fetch_assoc();

                $query = '';
                $query .= $resultado['nome'] != Post::get('editarNome') ? 'nome=' . $this->validar('nome', Post::get('editarNome')) . ', ' : '';
                $query .= $resultado['privilegio'] != Post::get('editarPrivilegio') ? 'privilegio=' . $this->validar('inteiro', Post::get('editarPrivilegio'), 0, 3) . ', ' : '';

                if (!strlen(Post::get('editarSenha')) == 0) $query .= 'senha=PASSWORD(' . $this->validar('senha', Post::get('editarSenha')) . '), ';

                if (strlen($query) == 0) throw new InvalidArgumentException('Nada para editar!');

                if ($this->queryUsuario('UPDATE', 'id=' . $this->escape(Post::get('editarID')), substr($query, 0, -2))) {
                    Session::set('status', 'Sucesso!');
                    header('Location: ?pagina=usuarios');
                    return;
                }
            }
        } catch (InvalidArgumentException | mysqli_sql_exception $e) {
            $tpl->SCRIPT = '<script defer>setTimeout(() => showSnackbar("' . $e->getMessage() . '"), 1);</script>';

            $tpl->NOME = Post::get('editarNome');
            $tpl->__set('PRIVILEGIO_' . Post::get('editarPrivilegio'), 'selected');
            $tpl->VALUE_SENHA = 'value=' . Post::get('editarSenha');
        }

        $tpl->addFile('TOPO', 'topo.html');

        $tpl->TITULO = 'Gerenciar Usuários - Editar Usuário';
        $tpl->ELEMENTO = "<script defer>document.getElementById('usuarios-alterar').classList.add('active');</script>";

        $tpl->block('ALTERAR');
        $tpl->show();
    }

    function remover()
    {
        if (Get::has('id')) {
            if ($this->queryUsuario('SELECT', 'id=' . $this->escape(Get::get('id'))) == 0) Session::set('status', 'Usuário não encontrado!');
            else Session::set('status', $this->queryUsuario('DELETE', 'id=' . $this->escape(Get::get('id'))) ? 'Sucesso!' : 'Falha!');
        } else {
            Session::set('status', 'Escolha o usuário para excluir!');
        }

        header('Location: ?pagina=usuarios');
    }

    function pesquisar()
    {
        if (!Post::has('pesquisa') || Post::get('pesquisa') == '') {
            header('Location: ?pagina=usuarios');
            return;
        }

        if (is_int(Post::get('pesquisa'))) $resultado = $this->queryUsuario('SELECT', 'id=' . $this->escape(Post::get('pesquisa')));
        else $resultado = $this->queryUsuario('SELECT', 'usuario LIKE "%' . Post::get('pesquisa') . '%" OR nome LIKE "%' . Post::get('pesquisa') . '%"');

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
