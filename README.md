# Sistema de Reservas | Churrascaria Pampulha

Aplicativo e website da churrascaria para registrar reservas e consultar agenda
por dia, semana ou mês. Ele usa Supabase como banco de dados online, permitindo
que tablets e computadores vejam as mesmas reservas em tempo real ao acessar o
mesmo endereço do sistema.

## Funções

- Login por e-mail e senha.
- Operador comum cria reservas.
- Operador nível 2 visualiza, altera e exclui reservas.
- Operador nível 3 faz tudo dos níveis 1 e 2 e também adiciona usuários.
- Filtros por dia, semana, mês e status.
- Resumo de total de reservas, pessoas, confirmadas e pendentes.
- Banco online via Supabase.
- Atualização em tempo real entre dispositivos logados.

## Configurar o banco online

1. Crie um projeto em https://supabase.com.
2. Abra o SQL Editor do Supabase.
3. Execute o conteúdo do arquivo `supabase.schema.sql`.
4. Em Database > Replication, habilite Realtime para a tabela `reservations`.
5. Em Authentication > Users, crie os usuários dos operadores.
6. Copie o UUID de cada usuário criado.
7. No SQL Editor, rode inserts na tabela `profiles`, usando `operador` ou `nivel_2`.

## Criar o primeiro nível 3

Para o usuário administrador inicial:

1. No Supabase, vá em Authentication > Users.
2. Crie o usuário com o e-mail `Matheusdyas4@gmail.com`.
3. Defina a senha diretamente no painel do Supabase.
4. Copie o UUID do usuário criado.
5. Abra `supabase/create-initial-level3-profile.sql`.
6. Troque `COLE_AQUI_O_UUID_DO_USUARIO` pelo UUID.
7. Execute o SQL no Supabase.

Não salve senhas em arquivos do repositório, principalmente se o repositório for
público.

## Permitir que nível 3 adicione usuários

O app chama a Edge Function `supabase/functions/create-user`.

Para publicar a função:

```bash
supabase functions deploy create-user
```

No painel do Supabase, confirme que as variáveis `SUPABASE_URL` e
`SUPABASE_SERVICE_ROLE_KEY` estão disponíveis para a função. A service role key
deve ficar somente no ambiente do Supabase, nunca no frontend.

Exemplo:

```sql
insert into public.profiles (id, full_name, email, role)
values
  ('UUID_DO_OPERADOR', 'Operador Atendimento', 'operador@exemplo.com', 'operador'),
  ('UUID_DO_SUPERVISOR', 'Supervisor Pampulha', 'supervisor@exemplo.com', 'nivel_2');
```

## Conectar o app ao Supabase

O app já vem configurado com a URL do projeto Supabase e a publishable key. Os
colaboradores só precisam abrir o sistema e fazer login.

Se algum dia trocar de projeto Supabase, clique em **Banco online** no painel e
cole novamente:

- URL do projeto Supabase.
- Publishable key.

Esses dados ficam salvos no computador ou tablet usado.

## Arquivo EXE

O arquivo `ChurrascariaPampulhaReservas.exe` abre o sistema no navegador padrão
do computador. Ele copia os arquivos para
`%LOCALAPPDATA%\ChurrascariaPampulhaReservas` e abre o app sempre dessa pasta,
mantendo a configuração local estável.

## Usar em tablet e computador

Depois de configurado, publique estes arquivos em uma hospedagem estática ou abra
o mesmo endereço nos dispositivos. Como o banco é online, todos usam os mesmos
dados.

Para teste local, basta abrir `index.html` no navegador.
