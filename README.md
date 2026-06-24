# 🔥 Churrascaria Pampulha - Novo Site

Site moderno, responsivo e interativo da Churrascaria Pampulha.

## ✨ Características

- **Design Responsivo**: Funciona perfeitamente em desktop, tablet e mobile
- **Logo Grande**: Logo da churrascaria bem dimensionada na navbar
- **Botão WhatsApp Flutuante**: Contacte direto pelo WhatsApp com um clique
- **Seções Completas**: Início, Cardápio, Rodízios, Eventos, Quem Somos, Contato
- **Animações Suaves**: Efeitos visuais modernos e agradáveis
- **Formulário de Contato**: Para que clientes entrem em contato diretamente

## 📁 Arquivos

- `index.html` - Estrutura HTML da página
- `style.css` - Estilos CSS modernos e responsivos
- `script.js` - Interatividade e animações
- `Logo Branca carroça MC.png` - Logo da churrascaria
- `whatsapp-icon.png` - Ícone do WhatsApp

## 📱 Contato

- **Telefone**: (31) 3582-5158
- **WhatsApp**: +55 31 84449047
- **Email**: contato@churrascariapampulha.com.br
- **Endereço**: Av. Pedro I, 568 - Itapoã, Belo Horizonte - MG

## 🚀 Como Usar

1. Abra `index.html` no navegador
2. Ou faça upload para um servidor web
3. O site é totalmente responsivo e funciona em qualquer dispositivo

## 🔐 Área do Funcionário

Acesso restrito à equipe (link "Área Funcionário" no rodapé), com banco MySQL via PHP/PDO e 3 níveis de permissão:

- **Nível 1 - Atendente**: cadastra e visualiza reservas (cliente, telefone, churrascaria, data do pedido, data/hora/qtde
  de pessoas da reserva, valor, status, confirmação, observação), atualiza confirmação/comparecimento,
  cadastra mesas do espaço.
- **Nível 2 - Gerente**: tudo do atendente + exclui reservas/mesas + cadastra/gerencia atendentes.
- **Nível 3 - Nível Superior**: acesso total, incluindo cadastro de gerentes e outros níveis superiores.

Na tela "Mesas" (acessível pelo menu, ao lado de "Funcionários") é possível cadastrar a quantidade de mesas
do espaço, escolhendo entre 2, 4 ou 6 lugares por mesa — a tela mostra o total de mesas e de lugares disponíveis.

Arquivos: `area-reservas.php` (login), `painel-reservas.php` (reservas), `mesas.php` (mesas do espaço),
`funcionarios.php` (gestão de equipe, nível ≥ 2), `trocar-senha.php`, `logout.php`, `auth.php`/`config.php`
(sessão e conexão com o banco), `schema.sql` (estrutura das tabelas) e `gerar-senha.php` (utilitário de uso
único para criar o primeiro admin).

### Deploy no Hostinger

1. No phpMyAdmin do banco (ex.: `u654041352_Reserva`), importe `schema.sql` na aba "Importar" ou "SQL".
   Isso já cria o admin inicial (usuário `Matheus.dias`, nível 3) — troque a senha pela tela "Trocar senha"
   depois do primeiro login.
2. Edite `config.php` com o host/usuário/senha reais do banco (hPanel > Bancos de Dados > Gerenciar).
3. **Importante:** suba o `config.php` (com as credenciais reais) **uma pasta acima da `public_html`**,
   não dentro dela. No Gerenciador de Arquivos do hPanel, navegue até a pasta do domínio (que contém a
   `public_html`), e coloque o `config.php` ali, ao lado da pasta `public_html` — não dentro. Assim, mesmo
   apagando/substituindo tudo dentro da `public_html` num deploy futuro, o `config.php` não é afetado e o
   site não cai com erro 500 por falta dele.
4. Suba todos os outros arquivos `.php`, `.html`, `.css`, `.js` e as imagens para dentro da `public_html`.
5. Faça login em `area-reservas.php` com o usuário admin e cadastre os demais funcionários pela tela
   "Funcionários".
6. Para criar outro admin via hash manual no futuro, use `gerar-senha.php` pelo navegador e depois
   **apague esse arquivo do servidor** — ele não deve ficar publicado.
7. Se o site já estava no ar antes da tabela `mesas` existir, não reimporte `schema.sql` inteiro (o INSERT
   do admin vai falhar por duplicidade). Em vez disso, rode só o trecho `CREATE TABLE IF NOT EXISTS mesas (...)`
   de `schema.sql` na aba "SQL" do phpMyAdmin.
8. Se o site já estava no ar antes dos campos novos da reserva (churrascaria, data do pedido, valor, status,
   confirmação, pessoas que compareceram, observação), rode este `ALTER TABLE` na aba "SQL" do phpMyAdmin pra atualizar a
   tabela `reservas` existente sem perder os dados:
   ```sql
   ALTER TABLE reservas
       ADD COLUMN churrascaria VARCHAR(60) NOT NULL DEFAULT 'Churrascaria Pampulha' AFTER telefone,
       ADD COLUMN data_pedido DATE NULL AFTER churrascaria,
       ADD COLUMN pessoas_compareceram SMALLINT UNSIGNED NULL AFTER pessoas,
       ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pessoas_compareceram,
       ADD COLUMN status_reserva VARCHAR(20) NOT NULL DEFAULT 'Reservado' AFTER valor,
       ADD COLUMN confirmacao VARCHAR(20) NOT NULL DEFAULT 'Pendente' AFTER status_reserva,
       ADD COLUMN observacao VARCHAR(255) NULL AFTER confirmacao;
   ```
9. Se a sua tabela já tem os campos antigos e falta apenas a churrascaria, rode só:
   ```sql
   ALTER TABLE reservas
       ADD COLUMN churrascaria VARCHAR(60) NOT NULL DEFAULT 'Churrascaria Pampulha' AFTER telefone;
   ```

## 📅 Desde 1982

A Churrascaria Pampulha oferece uma opção de churrasco nobre com ambiente familiar e atendimento especial há mais de 40 anos.

---

Desenvolvido com ❤️ para a Churrascaria Pampulha

## Feito no codex

- Atualização automática do dashboard a cada 30 segundos, sem recarregar a página inteira.
- Controle de sessão por inatividade: se o usuário ficar mais de 1 hora sem mexer no site, o login é desconectado.
- Criação do arquivo `atividade.php` para registrar atividade real do usuário no servidor.
- Criação do arquivo `sessao.js` para monitorar interações no navegador e redirecionar para logout quando a sessão expirar.
- Ajuste no refresh automático do dashboard para não renovar a sessão sozinho.
- Inclusão do controle de sessão nas páginas internas da área de funcionário.
- Mensagem na tela de login quando a sessão for encerrada por inatividade.

## Feito no Claude

- Backend PHP/MySQL completo da Área do Funcionário, com 3 níveis de acesso (atendente, gerente, nível superior).
- Tela de login (`area-reservas.php`), painel de reservas (`painel-reservas.php`), gestão de equipe (`funcionarios.php`),
  troca de senha (`trocar-senha.php`) e logout, além de `auth.php`/`config.php` (sessão e conexão com o banco) e
  `schema.sql` (estrutura das tabelas).
- Cadastro de admin inicial (`Matheus.dias`, nível 3) no `schema.sql` e utilitário `gerar-senha.php` para gerar hash
  de senha de novos admins.
- Campos completos na reserva: cliente, telefone, churrascaria, data do pedido, data/hora/quantidade de pessoas,
  pessoas que compareceram, valor, status, confirmação e observação — com máscaras de telefone e valor.
- Seletor de churrascaria nas reservas e no filtro do dashboard.
- Botão de edição das reservas no painel.
- Cadastro de mesas do espaço (2, 4 ou 6 lugares), movido para página própria (`mesas.php`) ao lado de Funcionários,
  com totais de mesas e lugares disponíveis.
- Dashboard inicial pós-login com resumo do dia, seletor de data e visões diária, semanal e mensal (em abas).
- Redesenho visual das páginas internas (login, painel, mesas, funcionários).
- Bloqueio anti-bruteforce no login da área de reservas.
- Botão de mostrar/ocultar senha no login e na troca de senha, com ajuste de posição no campo.
- Organização do `config.php` fora da `public_html` por segurança, com `config.example.php` como modelo.
- Cache-busting (`?v=`) no `style.css` para evitar cache desatualizado após deploys.
- Ajustes visuais do site público: remoção de gradiente escuro dos cards do cardápio, atualização do mapa de
  contato (Leaflet/Google Maps), simplificação da seção de contato e remoção de seções (Promoções, Happy Hour,
  Vouchers).
