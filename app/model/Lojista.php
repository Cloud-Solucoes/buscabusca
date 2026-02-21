<?php

use Adianti\Database\TRecord;

/**
 * Model de Lojista — recurso principal da API
 * 26 campos distribuídos em 6 etapas do formulário
 */
class Lojista extends TRecord
{
    const TABLENAME  = 'lojistas';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    // Etapa 1 — Identificação
    // tipo_pessoa, cnpj, razao_social, nome_fantasia, data_abertura
    // capital_social, regime_tributario, inscricao_estadual

    // Etapa 1 — Responsável Legal
    // resp_nome, resp_cpf, resp_email, resp_telefone

    // Etapa 2 — Segmento & Produto
    // segmento, descricao_produtos, origem_produtos, produtos_restritos

    // Etapa 3 — Estrutura & Logística
    // possui_loja, possui_estoque, estoque_cep, estoque_endereco, logistica_envio

    // Etapa 4 — Financeiro
    // emissao_nf, banco, agencia, conta, tipo_conta

    // Etapa 5 — Estratégia
    // volume_pedidos

    // Etapa 6 — Aceite
    // aceite_termos, aceite_veracidade

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Retorna todos os campos como array para serialização JSON
     */
    public function toArray($filter_attributes = null): array
    {
        return [
            'id'                  => (int) $this->id,
            // Identificação
            'tipo_pessoa'         => $this->tipo_pessoa,
            'cnpj'                => $this->cnpj,
            'razao_social'        => $this->razao_social,
            'nome_fantasia'       => $this->nome_fantasia,
            'data_abertura'       => $this->data_abertura,
            'capital_social'      => $this->capital_social ? (float) $this->capital_social : null,
            'regime_tributario'   => $this->regime_tributario,
            'inscricao_estadual'  => $this->inscricao_estadual,
            // Responsável Legal
            'resp_nome'           => $this->resp_nome,
            'resp_cpf'            => $this->resp_cpf,
            'resp_email'          => $this->resp_email,
            'resp_telefone'       => $this->resp_telefone,
            // Segmento & Produto
            'segmento'            => $this->segmento,
            'descricao_produtos'  => $this->descricao_produtos,
            'origem_produtos'     => $this->origem_produtos,
            'produtos_restritos'  => (bool) $this->produtos_restritos,
            // Estrutura & Logística
            'possui_loja'         => (bool) $this->possui_loja,
            'possui_estoque'      => (bool) $this->possui_estoque,
            'estoque_cep'         => $this->estoque_cep,
            'estoque_endereco'    => $this->estoque_endereco,
            'logistica_envio'     => $this->logistica_envio,
            // Financeiro
            'emissao_nf'          => $this->emissao_nf,
            'banco'               => $this->banco,
            'agencia'             => $this->agencia,
            'conta'               => $this->conta,
            'tipo_conta'          => $this->tipo_conta,
            // Estratégia
            'volume_pedidos'      => $this->volume_pedidos,
            // Aceite
            'aceite_termos'       => (bool) $this->aceite_termos,
            'aceite_veracidade'   => (bool) $this->aceite_veracidade,
            // Controle
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'user_id'             => $this->user_id ? (int) $this->user_id : null,
        ];
    }

    /**
     * Preenche o model a partir de array de dados
     */
    public function fromData(array $data): void
    {
        $fields = [
            'tipo_pessoa', 'cnpj', 'razao_social', 'nome_fantasia',
            'data_abertura', 'capital_social', 'regime_tributario', 'inscricao_estadual',
            'resp_nome', 'resp_cpf', 'resp_email', 'resp_telefone',
            'segmento', 'descricao_produtos', 'origem_produtos', 'produtos_restritos',
            'possui_loja', 'possui_estoque', 'estoque_cep', 'estoque_endereco', 'logistica_envio',
            'emissao_nf', 'banco', 'agencia', 'conta', 'tipo_conta',
            'volume_pedidos', 'aceite_termos', 'aceite_veracidade',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $this->$field = $data[$field];
            }
        }
    }
}
