const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost:8093';
const LOGIN_EMAIL = 'admin@buscabusca.com';
const LOGIN_SENHA = '123456';

/**
 * Helper: faz login e navega para /cadastro.html com sessao ativa
 */
async function loginAndGoToCadastro(page) {
  // Login via API para pegar token
  const res = await page.request.post(`${BASE}/login`, {
    data: { email: LOGIN_EMAIL, senha: LOGIN_SENHA },
  });
  const body = await res.json();
  const token = body.data.token;
  const usuario = body.data.usuario;

  // Setar sessionStorage antes de navegar
  await page.goto(`${BASE}/login.html`);
  await page.evaluate(({ token, usuario }) => {
    sessionStorage.setItem('bb_token', token);
    sessionStorage.setItem('bb_email', usuario.email);
    sessionStorage.setItem('bb_nome', usuario.nome || usuario.email);
    sessionStorage.setItem('bb_user_id', usuario.id);
  }, { token, usuario });

  await page.goto(`${BASE}/cadastro.html`);
  await page.waitForSelector('#wizardForm');
}

/**
 * Helper: digita caractere por caractere para disparar eventos input
 */
async function typeSlowly(page, selector, text) {
  const input = page.locator(selector);
  await input.click();
  await input.fill('');
  for (const char of text) {
    await input.press(char);
  }
}

// ==========================================================================
// ETAPA 1 — Mascaras de Identificacao
// ==========================================================================

test.describe('Etapa 1 — Mascaras de Identificacao', () => {

  test.beforeEach(async ({ page }) => {
    await loginAndGoToCadastro(page);
  });

  // --- CNPJ ---
  test('CNPJ: mascara formata 14 digitos como 00.000.000/0000-00', async ({ page }) => {
    await typeSlowly(page, '#cnpj', '11222333000181');
    const val = await page.inputValue('#cnpj');
    expect(val).toBe('11.222.333/0001-81');
  });

  test('CNPJ: mascara parcial com 8 digitos', async ({ page }) => {
    await typeSlowly(page, '#cnpj', '11222333');
    const val = await page.inputValue('#cnpj');
    expect(val).toBe('11.222.333');
  });

  test('CNPJ: nao aceita letras', async ({ page }) => {
    await typeSlowly(page, '#cnpj', '11abc222333000181');
    const val = await page.inputValue('#cnpj');
    // Deve conter apenas digitos formatados (letras ignoradas)
    expect(val.replace(/\D/g, '').length).toBeLessThanOrEqual(14);
    expect(val).not.toMatch(/[a-zA-Z]/);
  });

  test('CNPJ: validacao aceita CNPJ valido (11.222.333/0001-81)', async ({ page }) => {
    await typeSlowly(page, '#cnpj', '11222333000181');
    const feedback = page.locator('#cnpj_feedback');
    await expect(feedback).toBeVisible();
    await expect(feedback).toHaveClass(/text-green-600/);
    await expect(feedback).toHaveText('CNPJ valido');
  });

  test('CNPJ: validacao rejeita CNPJ invalido (11.111.111/1111-11)', async ({ page }) => {
    await typeSlowly(page, '#cnpj', '11111111111111');
    const feedback = page.locator('#cnpj_feedback');
    await expect(feedback).toBeVisible();
    await expect(feedback).toHaveClass(/text-red-600/);
    await expect(feedback).toHaveText('CNPJ invalido');
  });

  // --- CPF ---
  test('CPF: mascara formata 11 digitos como 000.000.000-00', async ({ page }) => {
    await typeSlowly(page, '#resp_cpf', '52998224725');
    const val = await page.inputValue('#resp_cpf');
    expect(val).toBe('529.982.247-25');
  });

  test('CPF: mascara parcial com 6 digitos', async ({ page }) => {
    await typeSlowly(page, '#resp_cpf', '529982');
    const val = await page.inputValue('#resp_cpf');
    expect(val).toBe('529.982');
  });

  test('CPF: nao aceita letras', async ({ page }) => {
    await typeSlowly(page, '#resp_cpf', '529abc98224725');
    const val = await page.inputValue('#resp_cpf');
    expect(val).not.toMatch(/[a-zA-Z]/);
  });

  test('CPF: validacao aceita CPF valido (529.982.247-25)', async ({ page }) => {
    await typeSlowly(page, '#resp_cpf', '52998224725');
    const feedback = page.locator('#cpf_feedback');
    await expect(feedback).toBeVisible();
    await expect(feedback).toHaveClass(/text-green-600/);
    await expect(feedback).toHaveText('CPF valido');
  });

  test('CPF: validacao rejeita CPF invalido (111.111.111-11)', async ({ page }) => {
    await typeSlowly(page, '#resp_cpf', '11111111111');
    const feedback = page.locator('#cpf_feedback');
    await expect(feedback).toBeVisible();
    await expect(feedback).toHaveClass(/text-red-600/);
    await expect(feedback).toHaveText('CPF invalido');
  });

  // --- Telefone ---
  test('Telefone: mascara celular (00) 00000-0000', async ({ page }) => {
    await typeSlowly(page, '#resp_telefone', '11987654321');
    const val = await page.inputValue('#resp_telefone');
    expect(val).toBe('(11) 98765-4321');
  });

  test('Telefone: mascara fixo (00) 0000-0000', async ({ page }) => {
    await typeSlowly(page, '#resp_telefone', '1132456789');
    const val = await page.inputValue('#resp_telefone');
    expect(val).toBe('(11) 3245-6789');
  });

  test('Telefone: mascara parcial com DDD', async ({ page }) => {
    await typeSlowly(page, '#resp_telefone', '11');
    const val = await page.inputValue('#resp_telefone');
    expect(val).toBe('(11');
  });

  test('Telefone: nao aceita letras', async ({ page }) => {
    await typeSlowly(page, '#resp_telefone', '11abc98765');
    const val = await page.inputValue('#resp_telefone');
    expect(val).not.toMatch(/[a-zA-Z]/);
  });

  // --- Capital Social (moeda) ---
  test('Capital Social: mascara formata como R$ 1.500,00', async ({ page }) => {
    await typeSlowly(page, '#capital_social', '150000');
    const val = await page.inputValue('#capital_social');
    expect(val).toBe('R$ 1.500,00');
  });

  test('Capital Social: mascara com centavos R$ 0,50', async ({ page }) => {
    await typeSlowly(page, '#capital_social', '50');
    const val = await page.inputValue('#capital_social');
    expect(val).toBe('R$ 0,50');
  });

  test('Capital Social: valor grande R$ 350.000,00', async ({ page }) => {
    await typeSlowly(page, '#capital_social', '35000000');
    const val = await page.inputValue('#capital_social');
    expect(val).toBe('R$ 350.000,00');
  });

  test('Capital Social: letras sao ignoradas, so digitos formatados', async ({ page }) => {
    await typeSlowly(page, '#capital_social', 'abc150000');
    const val = await page.inputValue('#capital_social');
    // Letras digitadas pelo usuario sao removidas; "R$" e parte da mascara
    const semPrefixo = val.replace(/^R\$\s?/, '');
    expect(semPrefixo).not.toMatch(/[a-zA-Z]/);
    expect(val).toBe('R$ 1.500,00');
  });
});

// ==========================================================================
// ETAPA 3 — Mascara de CEP
// ==========================================================================

test.describe('Etapa 3 — Mascara de CEP', () => {

  test.beforeEach(async ({ page }) => {
    await loginAndGoToCadastro(page);
    // Navegar ate etapa 3
    await page.click('#btnNext'); // -> etapa 2
    await page.click('#btnNext'); // -> etapa 3
  });

  test('CEP: mascara formata como 00000-000', async ({ page }) => {
    await typeSlowly(page, '#estoque_cep', '01310100');
    const val = await page.inputValue('#estoque_cep');
    expect(val).toBe('01310-100');
  });

  test('CEP: mascara parcial com 5 digitos', async ({ page }) => {
    await typeSlowly(page, '#estoque_cep', '01310');
    const val = await page.inputValue('#estoque_cep');
    expect(val).toBe('01310');
  });

  test('CEP: nao aceita letras', async ({ page }) => {
    await typeSlowly(page, '#estoque_cep', '01abc310100');
    const val = await page.inputValue('#estoque_cep');
    expect(val).not.toMatch(/[a-zA-Z]/);
  });
});

// ==========================================================================
// Wizard completo — Percorre todas as 6 etapas
// ==========================================================================

test.describe('Wizard completo — Navegacao e preenchimento', () => {

  test('Percorre as 6 etapas preenchendo todos os campos mascarados', async ({ page }) => {
    await loginAndGoToCadastro(page);

    // === ETAPA 1: Identificacao ===
    await expect(page.locator('.step-content[data-step="1"]')).toBeVisible();

    // Tipo pessoa PJ (ja vem marcado)
    await expect(page.locator('input[name="tipo_pessoa"][value="PJ"]')).toBeChecked();

    // CNPJ
    await typeSlowly(page, '#cnpj', '11222333000181');
    await expect(page.locator('#cnpj')).toHaveValue('11.222.333/0001-81');
    await expect(page.locator('#cnpj_feedback')).toHaveText('CNPJ valido');

    // Razao social e nome fantasia
    await page.fill('input[name="razao_social"]', 'Empresa Teste E2E LTDA');
    await page.fill('input[name="nome_fantasia"]', 'Teste E2E');

    // Data abertura
    await page.fill('input[name="data_abertura"]', '2023-06-15');

    // Capital social
    await typeSlowly(page, '#capital_social', '15000000');
    await expect(page.locator('#capital_social')).toHaveValue('R$ 150.000,00');

    // Regime tributario
    await page.selectOption('select[name="regime_tributario"]', 'Simples Nacional');

    // Inscricao estadual
    await page.fill('input[name="inscricao_estadual"]', '110042490114');

    // Responsavel
    await page.fill('input[name="resp_nome"]', 'Joao Teste');

    // CPF
    await typeSlowly(page, '#resp_cpf', '52998224725');
    await expect(page.locator('#resp_cpf')).toHaveValue('529.982.247-25');
    await expect(page.locator('#cpf_feedback')).toHaveText('CPF valido');

    // Email
    await page.fill('input[name="resp_email"]', 'joao@teste.com');

    // Telefone
    await typeSlowly(page, '#resp_telefone', '11987654321');
    await expect(page.locator('#resp_telefone')).toHaveValue('(11) 98765-4321');

    // Proximo
    await page.click('#btnNext');

    // === ETAPA 2: Segmento ===
    await expect(page.locator('.step-content[data-step="2"]')).toBeVisible();
    await page.selectOption('select[name="segmento"]', 'Eletronicos');
    await page.fill('textarea[name="descricao_produtos"]', 'Notebooks e acessorios');
    await page.selectOption('select[name="origem_produtos"]', 'Nacional');
    await page.click('#btnNext');

    // === ETAPA 3: Estrutura ===
    await expect(page.locator('.step-content[data-step="3"]')).toBeVisible();

    // CEP
    await typeSlowly(page, '#estoque_cep', '01310100');
    await expect(page.locator('#estoque_cep')).toHaveValue('01310-100');

    await page.fill('input[name="estoque_endereco"]', 'Av. Paulista, 1000');
    await page.selectOption('select[name="logistica_envio"]', 'Correios');
    await page.click('#btnNext');

    // === ETAPA 4: Financeiro ===
    await expect(page.locator('.step-content[data-step="4"]')).toBeVisible();
    await page.selectOption('select[name="emissao_nf"]', 'Automatica');
    await page.fill('input[name="banco"]', 'Banco do Brasil');
    await page.fill('input[name="agencia"]', '1234');
    await page.fill('input[name="conta"]', '56789-0');
    await page.selectOption('select[name="tipo_conta"]', 'Corrente');
    await page.click('#btnNext');

    // === ETAPA 5: Estrategia ===
    await expect(page.locator('.step-content[data-step="5"]')).toBeVisible();
    await page.selectOption('select[name="volume_pedidos"]', '50-200');
    await page.click('#btnNext');

    // === ETAPA 6: Aceite & Revisao ===
    await expect(page.locator('.step-content[data-step="6"]')).toBeVisible();

    // Verifica resumo contem dados mascarados corretos
    const resumo = await page.locator('#reviewSummary').textContent();
    expect(resumo).toContain('11.222.333/0001-81');    // CNPJ
    expect(resumo).toContain('529.982.247-25');         // CPF
    expect(resumo).toContain('(11) 98765-4321');        // Telefone
    expect(resumo).toContain('R$ 150.000,00');          // Capital social
    expect(resumo).toContain('Empresa Teste E2E LTDA'); // Razao social
    expect(resumo).toContain('01310-100');              // CEP

    // Aceites
    await page.check('input[name="aceite_termos"]');
    await page.check('input[name="aceite_veracidade"]');

    // Verifica botao submit visivel
    await expect(page.locator('#btnSubmit')).toBeVisible();
  });

  test('Navegacao anterior/proximo funciona corretamente', async ({ page }) => {
    await loginAndGoToCadastro(page);

    // Etapa 1
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 1 de 6');
    await expect(page.locator('#btnPrev')).not.toBeVisible();

    // Avanca ate etapa 3
    await page.click('#btnNext');
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 2 de 6');
    await page.click('#btnNext');
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 3 de 6');

    // Volta pra etapa 2
    await page.click('#btnPrev');
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 2 de 6');

    // Clica direto na etapa 5
    await page.click('.step-btn[data-step="5"]');
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 5 de 6');

    // Etapa 6: botao submit aparece, botao next some
    await page.click('#btnNext');
    await expect(page.locator('#stepLabel')).toHaveText('Etapa 6 de 6');
    await expect(page.locator('#btnNext')).not.toBeVisible();
    await expect(page.locator('#btnSubmit')).toBeVisible();
  });
});
