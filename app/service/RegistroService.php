<?php

use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;

/**
 * Serviço CRUD para o recurso Lojista (/registros)
 */
class RegistroService
{
    private string $database = 'buscabusca';
    private int $userId;

    public function __construct(Usuario $usuario)
    {
        $this->userId = (int) $usuario->id;
    }

    /**
     * GET /registros — lista lojistas do usuário autenticado
     */
    public function listar(): array
    {
        try {
            TTransaction::open($this->database);

            $criteria = new TCriteria;
            $criteria->add(new TFilter('user_id', '=', $this->userId));

            $repo     = new TRepository('Lojista');
            $lojistas = $repo->load($criteria);

            $result = [];
            if ($lojistas) {
                foreach ($lojistas as $lojista) {
                    $result[] = $lojista->toArray();
                }
            }

            TTransaction::close();

            LogService::info('REGISTROS_LISTAR', ['usuario_id' => $this->userId, 'total' => count($result)]);

            return [
                'success' => true,
                'data'    => $result,
                'message' => count($result) . ' registro(s) encontrado(s)',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('REGISTROS_LISTAR_ERROR', ['usuario_id' => $this->userId, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro ao listar registros',
            ];
        }
    }

    /**
     * POST /registros — cria novo lojista vinculado ao usuário autenticado
     */
    public function criar(array $dados): array
    {
        // Validação de campos obrigatórios
        $required = ['tipo_pessoa', 'aceite_termos', 'aceite_veracidade', 'resp_nome', 'resp_email'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $dados) || $dados[$field] === '' || $dados[$field] === null) {
                LogService::warning('REGISTROS_CRIAR_VALIDATION', ['usuario_id' => $this->userId, 'campo_faltante' => $field]);
                return [
                    'success'           => false,
                    '_validation_error' => true,
                    'message'           => "Campo obrigatório ausente: {$field}",
                ];
            }
        }

        try {
            TTransaction::open($this->database);

            $lojista          = new Lojista;
            $lojista->fromData($dados);
            $lojista->user_id    = $this->userId;
            $lojista->created_at = date('Y-m-d H:i:s');
            $lojista->updated_at = date('Y-m-d H:i:s');
            $lojista->store();

            $result = $lojista->toArray();

            TTransaction::close();

            LogService::info('REGISTROS_CRIADO', ['usuario_id' => $this->userId, 'lojista_id' => $result['id']]);

            return [
                'success' => true,
                'data'    => $result,
                'message' => 'Registro criado com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('REGISTROS_CRIAR_ERROR', ['usuario_id' => $this->userId, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro ao criar registro',
            ];
        }
    }

    /**
     * Verifica se um lojista existe e pertence ao usuário autenticado
     */
    private function exists(int $id): bool
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('id', '=', $id));
        $criteria->add(new TFilter('user_id', '=', $this->userId));
        $repo = new TRepository('Lojista');
        $results = $repo->load($criteria);
        return !empty($results);
    }

    /**
     * PUT /registros/{id} — atualiza lojista existente do usuário autenticado
     */
    public function atualizar(int $id, array $dados): array
    {
        try {
            TTransaction::open($this->database);

            if (!$this->exists($id)) {
                TTransaction::close();
                LogService::warning('REGISTROS_ATUALIZAR_NOT_FOUND', ['usuario_id' => $this->userId, 'lojista_id' => $id]);
                return [
                    'success'    => false,
                    'message'    => "Registro {$id} não encontrado",
                    '_not_found' => true,
                ];
            }

            $lojista = new Lojista($id);
            $lojista->fromData($dados);
            $lojista->user_id    = $this->userId;
            $lojista->updated_at = date('Y-m-d H:i:s');
            $lojista->store();

            $result = $lojista->toArray();

            TTransaction::close();

            LogService::info('REGISTROS_ATUALIZADO', ['usuario_id' => $this->userId, 'lojista_id' => $id]);

            return [
                'success' => true,
                'data'    => $result,
                'message' => 'Registro atualizado com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('REGISTROS_ATUALIZAR_ERROR', ['usuario_id' => $this->userId, 'lojista_id' => $id, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro ao atualizar registro',
            ];
        }
    }

    /**
     * DELETE /registros/{id} — remove lojista do usuário autenticado
     */
    public function deletar(int $id): array
    {
        try {
            TTransaction::open($this->database);

            if (!$this->exists($id)) {
                TTransaction::close();
                LogService::warning('REGISTROS_DELETAR_NOT_FOUND', ['usuario_id' => $this->userId, 'lojista_id' => $id]);
                return [
                    'success'    => false,
                    'message'    => "Registro {$id} não encontrado",
                    '_not_found' => true,
                ];
            }

            $lojista = new Lojista($id);
            $lojista->delete();

            TTransaction::close();

            LogService::info('REGISTROS_DELETADO', ['usuario_id' => $this->userId, 'lojista_id' => $id]);

            return [
                'success' => true,
                'message' => 'Registro removido com sucesso',
            ];
        } catch (\Exception $e) {
            TTransaction::rollback();
            LogService::error('REGISTROS_DELETAR_ERROR', ['usuario_id' => $this->userId, 'lojista_id' => $id, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Erro ao remover registro',
            ];
        }
    }
}
