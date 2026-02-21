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
                        'nome'  => $usuario->nome ?? null,
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
     * Registra um novo usuário e realiza login automático
     *
     * @return array ['success' => bool, 'data' => [...], 'message' => '...']
     */
    public function register(string $nome, string $email, string $senha): array
    {
        try {
            TTransaction::open($this->database);

            $existing = Usuario::findByEmail($email);

            if ($existing) {
                TTransaction::close();
                LogService::warning('REGISTER_EMAIL_TAKEN', ['email' => $email]);
                return [
                    'success'           => false,
                    '_validation_error' => true,
                    'message'           => 'Este e-mail já está cadastrado',
                ];
            }

            $usuario                  = new Usuario;
            $usuario->nome            = $nome;
            $usuario->email           = $email;
            $usuario->senha           = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $usuario->tentativas_login = 0;
            $usuario->store();

            $token = $usuario->generateToken();

            TTransaction::close();

            LogService::info('REGISTER_SUCCESS', ['email' => $email, 'usuario_id' => (int) $usuario->id]);

            return [
                'success' => true,
                'data' => [
                    'token'     => $token,
                    'expira_em' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                    'usuario'   => [
                        'id'    => (int) $usuario->id,
                        'email' => $usuario->email,
                        'nome'  => $usuario->nome,
                    ],
                ],
                'message' => 'Cadastro realizado com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('REGISTER_EXCEPTION', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro interno ao realizar cadastro',
            ];
        }
    }

    /**
     * Gera token de recuperação de senha (mock — retorna token na resposta)
     *
     * @return array ['success' => bool, 'data' => [...], 'message' => '...']
     */
    public function forgotPassword(string $email): array
    {
        try {
            TTransaction::open($this->database);

            $usuario = Usuario::findByEmail($email);

            if (!$usuario) {
                TTransaction::close();
                LogService::warning('FORGOT_PASSWORD_EMAIL_NOT_FOUND', ['email' => $email]);
                // Não revela se o e-mail existe ou não
                return [
                    'success' => true,
                    'data'    => ['reset_token' => null],
                    'message' => 'Se o e-mail estiver cadastrado, o token de recuperação será gerado',
                ];
            }

            $token = $usuario->generateResetToken();

            TTransaction::close();

            LogService::info('FORGOT_PASSWORD_TOKEN_GENERATED', ['email' => $email]);

            return [
                'success' => true,
                'data'    => ['reset_token' => $token],
                'message' => 'Token gerado (mock — em produção seria enviado por e-mail)',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('FORGOT_PASSWORD_EXCEPTION', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro interno ao processar solicitação',
            ];
        }
    }

    /**
     * Redefine a senha usando o token de recuperação
     *
     * @return array ['success' => bool, 'message' => '...']
     */
    public function resetPassword(string $token, string $novaSenha): array
    {
        try {
            TTransaction::open($this->database);

            $usuario = Usuario::findByResetToken($token);

            if (!$usuario) {
                TTransaction::close();
                LogService::warning('RESET_PASSWORD_INVALID_TOKEN', []);
                return [
                    'success' => false,
                    'message' => 'Token inválido ou expirado',
                ];
            }

            $usuario->senha = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
            $usuario->clearResetToken();

            TTransaction::close();

            LogService::info('RESET_PASSWORD_SUCCESS', ['usuario_id' => (int) $usuario->id]);

            return [
                'success' => true,
                'message' => 'Senha redefinida com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('RESET_PASSWORD_EXCEPTION', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro interno ao redefinir senha',
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
