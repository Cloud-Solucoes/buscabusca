<?php

use Adianti\Database\TTransaction;

/**
 * Serviço de autenticação — login, logout e validação de token
 */
class AuthService
{
    private string $database = 'buscabusca';

    /**
     * Realiza login e retorna token
     *
     * @return array ['success' => bool, 'data' => [...], 'message' => '...']
     */
    public function login(string $email, string $senha): array
    {
        try {
            TTransaction::open($this->database);

            $usuario = Usuario::findByEmail($email);

            if (!$usuario) {
                TTransaction::close();
                LogService::warning('LOGIN_USER_NOT_FOUND', ['email' => $email]);
                return [
                    'success' => false,
                    'message' => 'Credenciais inválidas',
                ];
            }

            if ($usuario->isLocked()) {
                TTransaction::close();
                LogService::warning('LOGIN_ACCOUNT_LOCKED', ['email' => $email]);
                return [
                    'success' => false,
                    'message' => 'Conta bloqueada temporariamente. Tente novamente em alguns minutos.',
                ];
            }

            if (!password_verify($senha, $usuario->senha)) {
                $usuario->incrementFailedAttempt();
                TTransaction::close();
                LogService::warning('LOGIN_WRONG_PASSWORD', [
                    'email'      => $email,
                    'tentativas' => (int) $usuario->tentativas_login,
                ]);
                return [
                    'success' => false,
                    'message' => 'Credenciais inválidas',
                ];
            }

            $usuario->resetFailedAttempts();
            $token = $usuario->generateToken();

            TTransaction::close();

            LogService::info('LOGIN_SUCCESS', ['email' => $email, 'usuario_id' => (int) $usuario->id]);

            return [
                'success' => true,
                'data' => [
                    'token'     => $token,
                    'expira_em' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                    'usuario'   => [
                        'id'    => (int) $usuario->id,
                        'email' => $usuario->email,
                    ],
                ],
                'message' => 'Login realizado com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('LOGIN_EXCEPTION', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro interno ao realizar login',
            ];
        }
    }

    /**
     * Invalida o token do usuário (logout)
     *
     * @return array ['success' => bool, 'message' => '...']
     */
    public function logout(string $token): array
    {
        try {
            TTransaction::open($this->database);

            $usuario = \Usuario::validateToken($token);

            if (!$usuario) {
                TTransaction::close();
                return [
                    'success' => false,
                    'message' => 'Token inválido ou expirado',
                ];
            }

            $usuario->logoutToken();

            TTransaction::close();

            LogService::info('LOGOUT_SUCCESS', ['usuario_id' => (int) $usuario->id]);

            return [
                'success' => true,
                'message' => 'Logout realizado com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('LOGOUT_EXCEPTION', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro interno ao realizar logout',
            ];
        }
    }

    /**
     * Valida o token Bearer do header Authorization
     *
     * @return \Usuario|null
     */
    public function validateToken(string $token): ?\Usuario
    {
        try {
            TTransaction::open($this->database);
            $usuario = \Usuario::validateToken($token);
            TTransaction::close();
            return $usuario;
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('VALIDATE_TOKEN_EXCEPTION', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
