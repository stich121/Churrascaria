-- 1. Crie primeiro o usuário em Authentication > Users no painel do Supabase.
-- 2. Use o e-mail: Matheusdyas4@gmail.com
-- 3. Defina a senha diretamente no painel do Supabase, nunca em arquivo público.
-- 4. Copie o UUID do usuário criado e substitua abaixo.

insert into public.profiles (id, full_name, email, role)
values (
  'COLE_AQUI_O_UUID_DO_USUARIO',
  'Matheus',
  'matheusdyas4@gmail.com',
  'nivel_3'
)
on conflict (id) do update
set
  full_name = excluded.full_name,
  email = excluded.email,
  role = excluded.role;
