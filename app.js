const DEFAULT_SUPABASE_URL = "https://szjslvjimtkurgffztaz.supabase.co";
const DEFAULT_SUPABASE_ANON_KEY = "sb_publishable_Ak3R3v72fSGSUn71Yk1Snw_yu1urxvU";
const CONFIG_STORAGE_KEY = "churrascaria-pampulha-supabase";

function loadConfig() {
  const savedConfig = localStorage.getItem(CONFIG_STORAGE_KEY);
  if (savedConfig) {
    try {
      return JSON.parse(savedConfig);
    } catch {
      localStorage.removeItem(CONFIG_STORAGE_KEY);
    }
  }

  return {
    url: DEFAULT_SUPABASE_URL,
    key: DEFAULT_SUPABASE_ANON_KEY,
  };
}

function hasValidConfig(config) {
  return Boolean(
    window.supabase &&
      config?.url?.startsWith("https://") &&
      config?.url?.includes(".supabase.co") &&
      config?.key?.length > 40
  );
}

let config = loadConfig();
let client = hasValidConfig(config)
  ? window.supabase.createClient(config.url, config.key)
  : null;

const state = {
  session: null,
  profile: null,
  reservations: [],
  period: "day",
  reservationsChannel: null,
};

const elements = {
  setupNotice: document.querySelector("#setupNotice"),
  setupForm: document.querySelector("#setupForm"),
  setupMessage: document.querySelector("#setupMessage"),
  supabaseUrl: document.querySelector("#supabaseUrl"),
  supabaseKey: document.querySelector("#supabaseKey"),
  loginView: document.querySelector("#loginView"),
  dashboardView: document.querySelector("#dashboardView"),
  loginForm: document.querySelector("#loginForm"),
  loginMessage: document.querySelector("#loginMessage"),
  email: document.querySelector("#email"),
  password: document.querySelector("#password"),
  logoutButton: document.querySelector("#logoutButton"),
  configureButton: document.querySelector("#configureButton"),
  usersButton: document.querySelector("#usersButton"),
  userRole: document.querySelector("#userRole"),
  baseDate: document.querySelector("#baseDate"),
  statusFilter: document.querySelector("#statusFilter"),
  newReservationButton: document.querySelector("#newReservationButton"),
  periodButtons: document.querySelectorAll(".period-button"),
  totalCount: document.querySelector("#totalCount"),
  guestCount: document.querySelector("#guestCount"),
  confirmedCount: document.querySelector("#confirmedCount"),
  pendingCount: document.querySelector("#pendingCount"),
  reservationRows: document.querySelector("#reservationRows"),
  emptyState: document.querySelector("#emptyState"),
  rangeTitle: document.querySelector("#rangeTitle"),
  permissionHint: document.querySelector("#permissionHint"),
  usersPanel: document.querySelector("#usersPanel"),
  userForm: document.querySelector("#userForm"),
  newUserName: document.querySelector("#newUserName"),
  newUserEmail: document.querySelector("#newUserEmail"),
  newUserPassword: document.querySelector("#newUserPassword"),
  newUserRole: document.querySelector("#newUserRole"),
  userMessage: document.querySelector("#userMessage"),
  userRows: document.querySelector("#userRows"),
  dialog: document.querySelector("#reservationDialog"),
  reservationForm: document.querySelector("#reservationForm"),
  dialogTitle: document.querySelector("#dialogTitle"),
  reservationId: document.querySelector("#reservationId"),
  customerName: document.querySelector("#customerName"),
  customerPhone: document.querySelector("#customerPhone"),
  reservationDate: document.querySelector("#reservationDate"),
  reservationTime: document.querySelector("#reservationTime"),
  guestAmount: document.querySelector("#guestAmount"),
  reservationArea: document.querySelector("#reservationArea"),
  reservationStatus: document.querySelector("#reservationStatus"),
  reservationNotes: document.querySelector("#reservationNotes"),
  reservationMessage: document.querySelector("#reservationMessage"),
  closeDialogButton: document.querySelector("#closeDialogButton"),
  cancelDialogButton: document.querySelector("#cancelDialogButton"),
  deleteReservationButton: document.querySelector("#deleteReservationButton"),
};

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function formatDate(isoDate) {
  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(new Date(`${isoDate}T00:00:00`));
}

function formatRole(role) {
  const labels = {
    operador: "Operador nível 1",
    nivel_2: "Operador nível 2",
    nivel_3: "Operador nível 3",
  };
  return labels[role] || "Operador";
}

function canManageReservations() {
  return ["nivel_2", "nivel_3"].includes(state.profile?.role);
}

function canManageUsers() {
  return state.profile?.role === "nivel_3";
}

function getRange() {
  const base = new Date(`${elements.baseDate.value || todayIso()}T00:00:00`);
  const start = new Date(base);
  const end = new Date(base);

  if (state.period === "week") {
    const day = base.getDay();
    start.setDate(base.getDate() - day);
    end.setDate(start.getDate() + 6);
  }

  if (state.period === "month") {
    start.setDate(1);
    end.setMonth(start.getMonth() + 1, 0);
  }

  return {
    start: start.toISOString().slice(0, 10),
    end: end.toISOString().slice(0, 10),
  };
}

function setView(view) {
  elements.loginView.classList.toggle("hidden", view !== "login");
  elements.dashboardView.classList.toggle("hidden", view !== "dashboard");
}

function setMessage(target, message, isError = true) {
  target.textContent = message || "";
  target.style.color = isError ? "var(--danger)" : "var(--ok)";
}

function showSetup(message = "") {
  elements.setupNotice.classList.remove("hidden");
  elements.supabaseUrl.value = hasValidConfig(config) ? config.url : "";
  elements.supabaseKey.value = hasValidConfig(config) ? config.key : "";
  elements.loginForm.querySelector("button").disabled = true;
  setMessage(elements.setupMessage, message);
}

function hideSetup() {
  elements.setupNotice.classList.add("hidden");
  elements.loginForm.querySelector("button").disabled = false;
  setMessage(elements.setupMessage, "");
}

function saveConfig(url, key) {
  config = { url: url.trim(), key: key.trim() };

  if (!hasValidConfig(config)) {
    throw new Error("Confira a URL e a chave anon pública do Supabase.");
  }

  localStorage.setItem(CONFIG_STORAGE_KEY, JSON.stringify(config));
  client = window.supabase.createClient(config.url, config.key);
}

async function loadProfile() {
  const { data, error } = await client
    .from("profiles")
    .select("id, full_name, role")
    .eq("id", state.session.user.id)
    .single();

  if (error) throw error;
  state.profile = data;
  elements.userRole.textContent = formatRole(data.role);
  elements.permissionHint.textContent = canManageReservations()
    ? "Você pode visualizar, alterar e excluir reservas."
    : "Você pode criar reservas. Alterações são restritas ao nível 2.";
  elements.usersButton.classList.toggle("hidden", !canManageUsers());
  elements.usersPanel.classList.toggle("hidden", !canManageUsers());
}

async function loadReservations() {
  const { start, end } = getRange();
  const status = elements.statusFilter.value;

  let query = client
    .from("reservations")
    .select("*")
    .gte("reservation_date", start)
    .lte("reservation_date", end)
    .order("reservation_date", { ascending: true })
    .order("reservation_time", { ascending: true });

  if (status !== "all") {
    query = query.eq("status", status);
  }

  const { data, error } = await query;
  if (error) throw error;

  state.reservations = data || [];
  renderReservations();
}

async function loadUsers() {
  if (!canManageUsers()) return;

  const { data, error } = await client
    .from("profiles")
    .select("id, full_name, email, role, created_at")
    .order("created_at", { ascending: false });

  if (error) throw error;
  renderUsers(data || []);
}

function renderUsers(users) {
  elements.userRows.innerHTML = "";

  for (const user of users) {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${escapeHtml(user.full_name)}</td>
      <td>${escapeHtml(user.email || "-")}</td>
      <td>${formatRole(user.role)}</td>
      <td>${user.created_at ? formatDate(user.created_at.slice(0, 10)) : "-"}</td>
    `;
    elements.userRows.appendChild(row);
  }
}

async function createUser() {
  if (!canManageUsers()) {
    throw new Error("Apenas operadores nível 3 podem adicionar usuários.");
  }

  const { data, error } = await client.functions.invoke("create-user", {
    body: {
      fullName: elements.newUserName.value.trim(),
      email: elements.newUserEmail.value.trim(),
      password: elements.newUserPassword.value,
      role: elements.newUserRole.value,
    },
  });

  if (error) throw error;
  if (data?.error) throw new Error(data.error);
}

function startRealtimeSync() {
  if (state.reservationsChannel) {
    client.removeChannel(state.reservationsChannel);
  }

  state.reservationsChannel = client
    .channel("reservations-sync")
    .on(
      "postgres_changes",
      { event: "*", schema: "public", table: "reservations" },
      () => loadReservations().catch(console.error)
    )
    .subscribe();
}

function renderReservations() {
  const statusLabels = {
    confirmada: "Confirmada",
    pendente: "Pendente",
    cancelada: "Cancelada",
    concluida: "Concluída",
  };

  const totalGuests = state.reservations.reduce((sum, item) => sum + Number(item.guest_amount || 0), 0);
  const confirmed = state.reservations.filter((item) => item.status === "confirmada").length;
  const pending = state.reservations.filter((item) => item.status === "pendente").length;

  elements.totalCount.textContent = state.reservations.length;
  elements.guestCount.textContent = totalGuests;
  elements.confirmedCount.textContent = confirmed;
  elements.pendingCount.textContent = pending;

  const { start, end } = getRange();
  const rangeNames = {
    day: `Reservas de ${formatDate(start)}`,
    week: `Reservas de ${formatDate(start)} a ${formatDate(end)}`,
    month: `Reservas de ${formatDate(start)} a ${formatDate(end)}`,
  };
  elements.rangeTitle.textContent = rangeNames[state.period];

  elements.reservationRows.innerHTML = "";
  elements.emptyState.classList.toggle("hidden", state.reservations.length > 0);

  for (const reservation of state.reservations) {
    const row = document.createElement("tr");
    const canEdit = canManageReservations();
    row.innerHTML = `
      <td>${formatDate(reservation.reservation_date)}</td>
      <td>${reservation.reservation_time.slice(0, 5)}</td>
      <td>
        <strong>${escapeHtml(reservation.customer_name)}</strong><br />
        <small>${escapeHtml(reservation.area || "salao")}</small>
      </td>
      <td>${reservation.guest_amount}</td>
      <td><span class="status-pill status-${reservation.status}">${statusLabels[reservation.status] || reservation.status}</span></td>
      <td>${escapeHtml(reservation.customer_phone)}</td>
      <td class="row-actions">
        <button type="button" data-action="view" data-id="${reservation.id}">${canEdit ? "Editar" : "Ver"}</button>
      </td>
    `;
    elements.reservationRows.appendChild(row);
  }
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function resetReservationForm() {
  elements.reservationForm.reset();
  elements.reservationId.value = "";
  elements.reservationDate.value = elements.baseDate.value || todayIso();
  elements.reservationTime.value = "19:00";
  elements.guestAmount.value = "2";
  elements.reservationStatus.value = "confirmada";
  elements.deleteReservationButton.classList.add("hidden");
  setMessage(elements.reservationMessage, "");
}

function openReservationDialog(reservation = null) {
  resetReservationForm();
  const editing = Boolean(reservation);
  const readOnly = editing && !canManageReservations();

  elements.dialogTitle.textContent = editing ? "Detalhes da reserva" : "Nova reserva";
  elements.deleteReservationButton.classList.toggle("hidden", !editing || !canManageReservations());

  if (reservation) {
    elements.reservationId.value = reservation.id;
    elements.customerName.value = reservation.customer_name;
    elements.customerPhone.value = reservation.customer_phone;
    elements.reservationDate.value = reservation.reservation_date;
    elements.reservationTime.value = reservation.reservation_time.slice(0, 5);
    elements.guestAmount.value = reservation.guest_amount;
    elements.reservationArea.value = reservation.area || "salao";
    elements.reservationStatus.value = reservation.status;
    elements.reservationNotes.value = reservation.notes || "";
  }

  [...elements.reservationForm.elements].forEach((field) => {
    if (!["button", "submit", "hidden"].includes(field.type)) {
      field.disabled = readOnly;
    }
  });

  elements.reservationForm.querySelector('button[type="submit"]').classList.toggle("hidden", readOnly);
  elements.dialog.showModal();
}

function closeReservationDialog() {
  elements.dialog.close();
}

function getReservationPayload() {
  return {
    customer_name: elements.customerName.value.trim(),
    customer_phone: elements.customerPhone.value.trim(),
    reservation_date: elements.reservationDate.value,
    reservation_time: elements.reservationTime.value,
    guest_amount: Number(elements.guestAmount.value),
    area: elements.reservationArea.value,
    status: elements.reservationStatus.value,
    notes: elements.reservationNotes.value.trim() || null,
  };
}

async function saveReservation() {
  const id = elements.reservationId.value;
  const payload = getReservationPayload();
  const submitButton = elements.reservationForm.querySelector('button[type="submit"]');

  submitButton.disabled = true;
  setMessage(elements.reservationMessage, "");

  try {
    if (id) {
      if (!canManageReservations()) throw new Error("Apenas operadores nível 2 ou 3 podem alterar reservas.");
      const { error } = await client.from("reservations").update(payload).eq("id", id);
      if (error) throw error;
    } else {
      const { error } = await client.from("reservations").insert({
        ...payload,
        created_by: state.session.user.id,
      });
      if (error) throw error;
    }

    closeReservationDialog();
    await loadReservations();
  } catch (error) {
    setMessage(elements.reservationMessage, error.message || "Não foi possível salvar a reserva.");
  } finally {
    submitButton.disabled = false;
  }
}

async function deleteReservation() {
  const id = elements.reservationId.value;
  if (!id || !canManageReservations()) return;

  const confirmed = window.confirm("Excluir esta reserva permanentemente?");
  if (!confirmed) return;

  const { error } = await client.from("reservations").delete().eq("id", id);
  if (error) {
    setMessage(elements.reservationMessage, error.message || "Não foi possível excluir.");
    return;
  }

  closeReservationDialog();
  await loadReservations();
}

async function boot() {
  elements.baseDate.value = todayIso();

  if (!hasValidConfig(config)) {
    showSetup("Configure o banco online para ativar o login.");
    setView("login");
    return;
  }

  hideSetup();
  const { data } = await client.auth.getSession();
  state.session = data.session;

  if (!state.session) {
    setView("login");
    return;
  }

  await loadProfile();
  setView("dashboard");
  startRealtimeSync();
  await loadReservations();
  await loadUsers();
}

elements.setupForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    saveConfig(elements.supabaseUrl.value, elements.supabaseKey.value);
    hideSetup();
    setView("login");
    setMessage(elements.loginMessage, "Banco online configurado. Agora faça login.", false);
  } catch (error) {
    showSetup(error.message || "Não foi possível salvar a configuração.");
  }
});

elements.loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  setMessage(elements.loginMessage, "");

  if (!client) {
    showSetup("Configure o banco online antes de entrar.");
    return;
  }

  const { data, error } = await client.auth.signInWithPassword({
    email: elements.email.value,
    password: elements.password.value,
  });

  if (error) {
    setMessage(elements.loginMessage, "E-mail ou senha inválidos.");
    return;
  }

  state.session = data.session;
  await loadProfile();
  setView("dashboard");
  startRealtimeSync();
  await loadReservations();
  await loadUsers();
});

elements.configureButton.addEventListener("click", () => {
  showSetup("");
  window.scrollTo({ top: 0, behavior: "smooth" });
});

elements.logoutButton.addEventListener("click", async () => {
  if (state.reservationsChannel) {
    client.removeChannel(state.reservationsChannel);
    state.reservationsChannel = null;
  }
  await client.auth.signOut();
  state.session = null;
  state.profile = null;
  setView("login");
});

elements.periodButtons.forEach((button) => {
  button.addEventListener("click", async () => {
    state.period = button.dataset.period;
    elements.periodButtons.forEach((item) => item.classList.toggle("active", item === button));
    await loadReservations();
  });
});

elements.baseDate.addEventListener("change", loadReservations);
elements.statusFilter.addEventListener("change", loadReservations);
elements.newReservationButton.addEventListener("click", () => openReservationDialog());
elements.usersButton.addEventListener("click", async () => {
  elements.usersPanel.classList.toggle("hidden");
  if (!elements.usersPanel.classList.contains("hidden")) {
    await loadUsers();
  }
});
elements.closeDialogButton.addEventListener("click", closeReservationDialog);
elements.cancelDialogButton.addEventListener("click", closeReservationDialog);
elements.deleteReservationButton.addEventListener("click", deleteReservation);

elements.userForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const button = elements.userForm.querySelector("button");
  button.disabled = true;
  setMessage(elements.userMessage, "");

  try {
    await createUser();
    elements.userForm.reset();
    setMessage(elements.userMessage, "Usuário criado com sucesso.", false);
    await loadUsers();
  } catch (error) {
    setMessage(elements.userMessage, error.message || "Não foi possível criar o usuário.");
  } finally {
    button.disabled = false;
  }
});

elements.reservationRows.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-action='view']");
  if (!button) return;
  const reservation = state.reservations.find((item) => item.id === button.dataset.id);
  if (reservation) openReservationDialog(reservation);
});

elements.reservationForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  await saveReservation();
});

boot().catch((error) => {
  console.error(error);
  setMessage(elements.loginMessage, error.message || "Erro ao iniciar o sistema.");
});
