# Guia de Integração — Formulário Web → SGP (Criação de Contatos)

> Este guia é autocontido: quem for implementar o formulário **não precisa** ter acesso ao
> código-fonte do SGP, apenas ao contrato de API descrito aqui.

## 1. Objetivo

Uma aplicação web externa (formulário de captação de leads/contatos) deve enviar os dados
preenchidos para o SGP, que:

1. Cria (ou atualiza, se já existir) um `Contact`;
2. Aplica automaticamente uma **tag de origem** a esse contato — a tag é resolvida no
   backend a partir do token usado, o formulário **não** precisa enviar nenhuma tag;
3. Se houver uma automação de campanha configurada para essa tag (ex.: mensagem de
   boas-vindas via WhatsApp), ela é disparada automaticamente para contatos novos.

## 2. Campos do formulário

| Campo no formulário | Campo no payload da API | Tipo         | Obrigatório | Regras                          |
|----------------------|--------------------------|--------------|-------------|----------------------------------|
| Nome                 | `name`                   | string       | sim*        | máx. 255 caracteres             |
| E-mail               | `email`                  | string/email | sim*        | máx. 255, formato de e-mail válido |
| WhatsApp             | `whatsapp`                | string       | não         | máx. 30 — enviar com DDI+DDD (ex: `5511988887777`) |
| Cidade               | `city`                    | string       | não         | máx. 100                        |
| Bairro               | `neighborhood`             | string       | não         | máx. 255                        |

\* A API exige que **pelo menos um** entre `name` e `email` seja enviado. Como o formulário
tem os dois campos, recomenda-se marcar ambos como obrigatórios na UI para melhor
experiência e para garantir a deduplicação (ver seção 4).

### Campos personalizados (opcional)

Além dos campos fixos acima, o SGP permite que cada tenant defina **campos personalizados**
próprios (em Configurações → Campos personalizados). Para enviar valores para eles, use o
objeto `custom_fields` no payload, com a **chave (`key`)** de cada campo definida no SGP:

```json
{
  "name": "Maria Souza",
  "email": "maria@example.com",
  "custom_fields": {
    "zona_eleitoral_pref": "045",
    "aceita_visita": true
  }
}
```

- Peça ao administrador do SGP a lista de `key`s dos campos personalizados que deseja
  preencher (cada campo tem uma chave própria, visível na tela de Configurações → Campos
  personalizados) — não há como descobri-las por este token, que só cria contatos.
- Qualquer chave que não corresponda a um campo personalizado ativo do tenant é **ignorada
  silenciosamente** (não gera erro) — proteção contra digitação errada ou campo removido
  depois que a integração já estava em produção.
- Tipo do valor esperado por tipo de campo: `text`/`textarea` → string; `number` → número;
  `date` → string `AAAA-MM-DD`; `select` → string (uma das opções cadastradas); `checkbox` →
  `true`/`false`.

### Campo opcional — consentimento LGPD

| Campo no payload | Tipo    | Observação |
|-------------------|---------|------------|
| `lgpd_consent`     | boolean | Envie `true` somente se o formulário tiver um checkbox de consentimento marcado pelo usuário. **Automações de campanha só disparam para contatos com `lgpd_consent = true`.** Sem esse campo (ou `false`), o contato é criado normalmente, mas nenhuma automação de mensagem é enviada a ele. |

## 3. Pré-requisito: obter o token de integração

Antes de codificar o formulário, um administrador do SGP precisa criar uma **fonte de
integração**, que gera o token e a tag de origem associada. Isso pode ser feito pela
própria interface do SGP, em **Configurações → Integrações → Nova fonte** (o token só é
exibido uma vez, na hora da criação) — ou via API diretamente:

```
POST {SGP_BASE_URL}/api/v1/integration-sources
Authorization: Bearer {token de sessão do admin no SGP}   ← autenticação normal do SGP, não o token de integração
Content-Type: application/json

{
  "name": "Formulário site institucional"
}
```

Resposta (201):

```json
{
  "source": {
    "ulid": "01M0...ABC",
    "name": "Formulário site institucional",
    "tag": { "id": 12, "name": "origem: Formulário site institucional" },
    "is_active": true
  },
  "token": "aBcD1234...XYZ"
}
```

**O `token` só é exibido nessa resposta (e na de `regenerate-token`).** Copie e guarde-o em
local seguro — é ele que a aplicação externa usará no header `Authorization` das
requisições públicas descritas a seguir. Se for perdido, é preciso gerar um novo via
`POST /api/v1/integration-sources/{ulid}/regenerate-token` (o token antigo é invalidado).

## 4. Endpoint público de criação de contato

```
POST {SGP_BASE_URL}/api/v1/integrations/contacts
Authorization: Bearer {token de integração obtido no passo 3}
Content-Type: application/json

{
  "name": "Maria Souza",
  "email": "maria@example.com",
  "whatsapp": "5511988887777",
  "city": "Fortaleza",
  "neighborhood": "Aldeota",
  "lgpd_consent": true
}
```

- Este endpoint **não usa** a sessão/login do SGP — a autenticação é feita só pelo token
  de integração no header `Authorization`.
- Limite de requisições: **60 por minuto** (HTTP 429 se exceder).
- Deduplicação automática: o SGP procura um contato existente por `email` → `cpf` → `phone`/`whatsapp`,
  nessa ordem. Se encontrar, **atualiza** os dados e apenas adiciona a tag de origem; se não
  encontrar, **cria** um novo contato. Ou seja, é seguro reenviar o mesmo lead mais de uma vez.

### Respostas

| Status | Quando ocorre | Corpo |
|--------|----------------|-------|
| `201 Created` | Contato criado/atualizado com sucesso | Objeto do contato (`id`, `name`, `email`, `whatsapp`, `city`, `tags`, etc.) |
| `401 Unauthorized` | Token ausente, inválido ou desativado | `{ "message": "..." }` |
| `422 Unprocessable Entity` | Payload inválido (ex.: nem `name` nem `email` enviados, e-mail mal formatado) | `{ "message": "...", "errors": { "email": ["..."] } }` (padrão de validação Laravel) |
| `429 Too Many Requests` | Limite de 60 req/min excedido | — |

## 5. Exemplo de requisição (curl)

```bash
curl -X POST "{SGP_BASE_URL}/api/v1/integrations/contacts" \
  -H "Authorization: Bearer aBcD1234...XYZ" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Maria Souza",
    "email": "maria@example.com",
    "whatsapp": "5511988887777",
    "city": "Fortaleza",
    "neighborhood": "Aldeota",
    "lgpd_consent": true
  }'
```

## 6. ⚠️ Segurança do token — não expor no frontend

O token de integração dá acesso de escrita ao SGP (criação de contatos). **Nunca** o
coloque diretamente em código JavaScript que roda no navegador (bundle público, HTML,
variável `VITE_*`/`NEXT_PUBLIC_*`/etc.) — qualquer pessoa pode abrir o DevTools/"Ver
código-fonte" e copiá-lo, e então usar seu limite de requisições ou poluir a base de
contatos do tenant.

**Padrão recomendado:** o formulário no navegador envia os dados para um endpoint do
**próprio backend** da aplicação externa (ex. uma função serverless, uma rota Express,
etc.), e é esse backend — nunca o navegador — quem guarda o token (em variável de
ambiente) e faz a chamada para o SGP.

```
[Navegador]  →  POST /submit (seu backend)  →  POST /api/v1/integrations/contacts (SGP)
                  token fica só aqui  ↑
```

Exemplo mínimo de proxy em Node/Express:

```js
// backend da aplicação externa — token NUNCA sai daqui
app.post('/submit', async (req, res) => {
  const { name, email, whatsapp, city, neighborhood, lgpdConsent } = req.body;

  const response = await fetch(`${process.env.SGP_BASE_URL}/api/v1/integrations/contacts`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${process.env.SGP_INTEGRATION_TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      name,
      email,
      whatsapp,
      city,
      neighborhood,
      lgpd_consent: !!lgpdConsent,
    }),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    return res.status(response.status).json(error);
  }

  return res.status(201).json(await response.json());
});
```

Se a aplicação externa for 100% estática (sem backend próprio), use uma função serverless
(Cloudflare Worker, Vercel/Netlify Function, AWS Lambda, etc.) com o mesmo papel: receber o
submit do formulário e repassar ao SGP guardando o token como variável de ambiente da
função, nunca no bundle do cliente.

## 7. Checklist de implementação

- [ ] Confirmar `{SGP_BASE_URL}` com o administrador do SGP
- [ ] Obter o token de integração (passo 3) e guardá-lo como variável de ambiente segura
      **no backend/serverless**, nunca no frontend
- [ ] Construir o formulário com os campos Nome, WhatsApp, E-mail, Cidade, Bairro
- [ ] (Opcional, recomendado) Adicionar checkbox de consentimento LGPD e mapear para `lgpd_consent`
- [ ] (Opcional) Se o formulário tiver campos além dos fixos, confirmar com o administrador as
      `key`s dos campos personalizados correspondentes e enviá-los em `custom_fields`
- [ ] Implementar o endpoint/proxy de backend que recebe o submit e chama a API do SGP
- [ ] Tratar e exibir ao usuário os erros `401`, `422` e `429`
- [ ] Testar o fluxo completo e confirmar no SGP que o contato foi criado com a tag de
      origem correta
