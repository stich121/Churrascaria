import { serve } from "https://deno.land/std@0.224.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

const allowedRoles = new Set(["operador", "nivel_2", "nivel_3"]);

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
    const authHeader = req.headers.get("Authorization") || "";
    const token = authHeader.replace("Bearer ", "");

    if (!supabaseUrl || !serviceRoleKey) {
      throw new Error("Variáveis SUPABASE_URL e SUPABASE_SERVICE_ROLE_KEY não configuradas.");
    }

    if (!token) {
      return json({ error: "Login obrigatório." }, 401);
    }

    const admin = createClient(supabaseUrl, serviceRoleKey);
    const { data: authData, error: authError } = await admin.auth.getUser(token);

    if (authError || !authData.user) {
      return json({ error: "Sessão inválida." }, 401);
    }

    const { data: callerProfile, error: profileError } = await admin
      .from("profiles")
      .select("role")
      .eq("id", authData.user.id)
      .single();

    if (profileError || callerProfile?.role !== "nivel_3") {
      return json({ error: "Apenas usuários nível 3 podem adicionar usuários." }, 403);
    }

    const body = await req.json();
    const action = String(body.action || "create");

    if (action === "update") {
      return await updateExistingUser(admin, body);
    }

    const fullName = String(body.fullName || "").trim();
    const email = String(body.email || "").trim().toLowerCase();
    const password = String(body.password || "");
    const role = String(body.role || "operador");

    if (!fullName || !email || password.length < 8 || !allowedRoles.has(role)) {
      return json({ error: "Dados inválidos para criar o usuário." }, 400);
    }

    const { data: createdUser, error: createError } = await admin.auth.admin.createUser({
      email,
      password,
      email_confirm: true,
      user_metadata: { full_name: fullName },
    });

    if (createError || !createdUser.user) {
      return json({ error: createError?.message || "Não foi possível criar o login." }, 400);
    }

    const { error: insertError } = await admin.from("profiles").upsert({
      id: createdUser.user.id,
      full_name: fullName,
      email,
      role,
    });

    if (insertError) {
      await admin.auth.admin.deleteUser(createdUser.user.id);
      return json({ error: insertError.message }, 400);
    }

    return json({
      user: {
        id: createdUser.user.id,
        email,
        fullName,
        role,
      },
    });
  } catch (error) {
    return json({ error: error instanceof Error ? error.message : "Erro inesperado." }, 500);
  }
});

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...corsHeaders,
      "Content-Type": "application/json",
    },
  });
}

async function updateExistingUser(admin: ReturnType<typeof createClient>, body: Record<string, unknown>) {
  const userId = String(body.userId || "").trim();
  const role = String(body.role || "");
  const password = String(body.password || "");

  if (!userId || !allowedRoles.has(role)) {
    return json({ error: "Dados inválidos para alterar o usuário." }, 400);
  }

  if (password && password.length < 8) {
    return json({ error: "A nova senha precisa ter pelo menos 8 caracteres." }, 400);
  }

  const updatePayload: { password?: string } = {};

  if (password) {
    updatePayload.password = password;
  }

  if (Object.keys(updatePayload).length > 0) {
    const { error: authUpdateError } = await admin.auth.admin.updateUserById(userId, updatePayload);

    if (authUpdateError) {
      return json({ error: authUpdateError.message }, 400);
    }
  }

  const { data: profile, error: profileError } = await admin
    .from("profiles")
    .update({ role })
    .eq("id", userId)
    .select("id, full_name, email, role")
    .single();

  if (profileError || !profile) {
    return json({ error: profileError?.message || "Perfil não encontrado." }, 400);
  }

  return json({
    user: {
      id: profile.id,
      email: profile.email,
      fullName: profile.full_name,
      role: profile.role,
    },
  });
}
