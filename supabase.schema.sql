create extension if not exists pgcrypto;

create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null,
  email text unique,
  role text not null default 'operador' check (role in ('operador', 'nivel_2', 'nivel_3')),
  created_at timestamptz not null default now()
);

create table if not exists public.reservations (
  id uuid primary key default gen_random_uuid(),
  customer_name text not null,
  customer_phone text not null,
  reservation_date date not null,
  reservation_time time not null,
  guest_amount integer not null check (guest_amount > 0),
  area text not null default 'salao',
  status text not null default 'confirmada' check (status in ('confirmada', 'pendente', 'cancelada', 'concluida')),
  notes text,
  created_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists reservations_date_time_idx
  on public.reservations (reservation_date, reservation_time);

create or replace function public.touch_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists reservations_touch_updated_at on public.reservations;
create trigger reservations_touch_updated_at
before update on public.reservations
for each row
execute function public.touch_updated_at();

create or replace function public.current_user_is_level_2()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.profiles
    where id = auth.uid()
      and role in ('nivel_2', 'nivel_3')
  );
$$;

create or replace function public.current_user_is_level_3()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.profiles
    where id = auth.uid()
      and role = 'nivel_3'
  );
$$;

alter table public.profiles enable row level security;
alter table public.reservations enable row level security;

drop policy if exists "profiles_select_own" on public.profiles;
create policy "profiles_select_own"
on public.profiles for select
to authenticated
using (id = auth.uid());

drop policy if exists "profiles_level2_select_all" on public.profiles;
drop policy if exists "profiles_level3_select_all" on public.profiles;
create policy "profiles_level3_select_all"
on public.profiles for select
to authenticated
using (public.current_user_is_level_3());

drop policy if exists "profiles_level3_insert" on public.profiles;
create policy "profiles_level3_insert"
on public.profiles for insert
to authenticated
with check (public.current_user_is_level_3());

drop policy if exists "profiles_level3_update" on public.profiles;
create policy "profiles_level3_update"
on public.profiles for update
to authenticated
using (public.current_user_is_level_3())
with check (public.current_user_is_level_3());

drop policy if exists "reservations_select_authenticated" on public.reservations;
create policy "reservations_select_authenticated"
on public.reservations for select
to authenticated
using (true);

drop policy if exists "reservations_insert_authenticated" on public.reservations;
create policy "reservations_insert_authenticated"
on public.reservations for insert
to authenticated
with check (created_by = auth.uid());

drop policy if exists "reservations_update_level2" on public.reservations;
create policy "reservations_update_level2"
on public.reservations for update
to authenticated
using (public.current_user_is_level_2())
with check (public.current_user_is_level_2());

drop policy if exists "reservations_delete_level2" on public.reservations;
create policy "reservations_delete_level2"
on public.reservations for delete
to authenticated
using (public.current_user_is_level_2());

-- Depois de criar usuários no Supabase Auth, cadastre os perfis assim:
-- insert into public.profiles (id, full_name, email, role)
-- values
--   ('UUID_DO_USUARIO_OPERADOR', 'Operador Caixa', 'operador@exemplo.com', 'operador'),
--   ('UUID_DO_USUARIO_NIVEL_2', 'Supervisor', 'supervisor@exemplo.com', 'nivel_2'),
--   ('UUID_DO_USUARIO_NIVEL_3', 'Matheus', 'Matheusdyas4@gmail.com', 'nivel_3');
