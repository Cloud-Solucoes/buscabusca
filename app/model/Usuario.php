<?php

use Adianti\Database\TRecord;
use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;

/**
 * Model de Usuário para autenticação
 */
class Usuario extends TRecord
{
    const TABLENAME  = 'usuarios';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    const MAX_ATTEMPTS    = 5;
    const LOCKOUT_MINUTES = 15;

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Busca usuário pelo e-mail
     */
    public static function findByEmail(string $email): ?self
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('email', '=', $email));

        $repo = new TRepository('Usuario');
        $results = $repo->load($criteria);

        return $results ? $results[0] : null;
    }

    /**
     * Gera token criptograficamente seguro e salva no banco (validade: 1 hora)
     */
    public function generateToken(): string
    {
        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->token          = $token;
        $this->token_expira_em = $expira;
        $this->store();

        return $token;
    }

    /**
     * Valida token e retorna o usuário ou null
     */
    public static function validateToken(string $token): ?self
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('token', '=', $token));

        $repo = new TRepository('Usuario');
        $results = $repo->load($criteria);

        if (!$results) {
            return null;
        }

        $usuario = $results[0];

        if (empty($usuario->token_expira_em)) {
            return null;
        }

        if (strtotime($usuario->token_expira_em) < time()) {
            return null;
        }

        return $usuario;
    }

    /**
     * Verifica se a conta está bloqueada por excesso de tentativas
     */
    public function isLocked(): bool
    {
        if (empty($this->bloqueado_ate)) {
            return false;
        }
        return strtotime($this->bloqueado_ate) > time();
    }

    /**
     * Incrementa contador de falhas e bloqueia a conta após MAX_ATTEMPTS
     */
    public function incrementFailedAttempt(): void
    {
        $tentativas = (int) $this->tentativas_login + 1;
        $this->tentativas_login = $tentativas;

        if ($tentativas >= self::MAX_ATTEMPTS) {
            $this->bloqueado_ate = date('Y-m-d H:i:s', strtotime('+' . self::LOCKOUT_MINUTES . ' minutes'));
        }

        $this->store();
    }

    /**
     * Zera contador de falhas e remove bloqueio após login bem-sucedido
     */
    public function resetFailedAttempts(): void
    {
        $this->tentativas_login = 0;
        $this->bloqueado_ate    = null;
        $this->store();
    }

    /**
     * Invalida o token (logout)
     */
    public function logoutToken(): void
    {
        $this->token           = null;
        $this->token_expira_em = null;
        $this->store();
    }

    /**
     * Gera token de recuperação de senha (validade: 1 hora)
     */
    public function generateResetToken(): string
    {
        $token  = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->reset_token        = $token;
        $this->reset_token_expiry = $expiry;
        $this->store();

        return $token;
    }

    /**
     * Busca usuário pelo token de recuperação de senha
     */
    public static function findByResetToken(string $token): ?self
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('reset_token', '=', $token));

        $repo    = new TRepository('Usuario');
        $results = $repo->load($criteria);

        if (!$results) {
            return null;
        }

        $usuario = $results[0];

        if (empty($usuario->reset_token_expiry)) {
            return null;
        }

        if (strtotime($usuario->reset_token_expiry) < time()) {
            return null;
        }

        return $usuario;
    }

    /**
     * Limpa o token de recuperação de senha após uso
     */
    public function clearResetToken(): void
    {
        $this->reset_token        = null;
        $this->reset_token_expiry = null;
        $this->store();
    }
}
