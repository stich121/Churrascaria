// Login de funcionário - credenciais de demonstração.
// Atenção: isso roda só no navegador, então não é seguro para proteger dados de verdade.
// Para um controle de acesso real, troque por um login validado em um servidor/backend.
const FUNCIONARIO_USUARIO = 'funcionario';
const FUNCIONARIO_SENHA = 'pampulha2026';

const loginForm = document.getElementById('loginFuncionarioForm');
if (loginForm) {
    const erro = document.getElementById('loginErro');

    loginForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const usuario = document.getElementById('usuario').value.trim();
        const senha = document.getElementById('senha').value;

        if (usuario === FUNCIONARIO_USUARIO && senha === FUNCIONARIO_SENHA) {
            sessionStorage.setItem('pampulhaFuncionarioLogado', 'true');
            window.location.href = 'painel-reservas.html';
        } else {
            erro.textContent = 'Usuário ou senha incorretos.';
        }
    });
}

const reservasTbody = document.getElementById('reservasTbody');
if (reservasTbody) {
    if (sessionStorage.getItem('pampulhaFuncionarioLogado') !== 'true') {
        window.location.href = 'area-reservas.html';
    } else {
        const logoutBtn = document.getElementById('logoutBtn');
        logoutBtn.addEventListener('click', function () {
            sessionStorage.removeItem('pampulhaFuncionarioLogado');
            window.location.href = 'index.html';
        });

        const reservaForm = document.getElementById('reservaForm');
        const vazio = document.getElementById('reservasVazio');

        const carregarReservas = () => JSON.parse(localStorage.getItem('pampulhaReservas') || '[]');
        const salvarReservas = (reservas) => localStorage.setItem('pampulhaReservas', JSON.stringify(reservas));

        const formatarData = (data) => {
            const [ano, mes, dia] = data.split('-');
            return `${dia}/${mes}/${ano}`;
        };

        function renderizarReservas() {
            const reservas = carregarReservas();
            reservasTbody.innerHTML = '';

            reservas
                .slice()
                .sort((a, b) => `${a.data}${a.hora}`.localeCompare(`${b.data}${b.hora}`))
                .forEach((reserva) => {
                    const tr = document.createElement('tr');

                    [reserva.nome, reserva.telefone, formatarData(reserva.data), reserva.hora, reserva.pessoas].forEach((valor) => {
                        const td = document.createElement('td');
                        td.textContent = valor;
                        tr.appendChild(td);
                    });

                    const tdAcao = document.createElement('td');
                    const btnRemover = document.createElement('button');
                    btnRemover.type = 'button';
                    btnRemover.className = 'btn-remover-reserva';
                    btnRemover.title = 'Remover reserva';
                    btnRemover.innerHTML = '<i class="fa-solid fa-trash"></i>';
                    btnRemover.addEventListener('click', () => {
                        const restantes = carregarReservas().filter((r) => r.id !== reserva.id);
                        salvarReservas(restantes);
                        renderizarReservas();
                    });
                    tdAcao.appendChild(btnRemover);
                    tr.appendChild(tdAcao);

                    reservasTbody.appendChild(tr);
                });

            vazio.style.display = reservas.length ? 'none' : 'block';
        }

        reservaForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const reservas = carregarReservas();

            reservas.push({
                id: Date.now(),
                nome: document.getElementById('resNome').value.trim(),
                telefone: document.getElementById('resTelefone').value.trim(),
                data: document.getElementById('resData').value,
                hora: document.getElementById('resHora').value,
                pessoas: document.getElementById('resPessoas').value
            });

            salvarReservas(reservas);
            renderizarReservas();
            reservaForm.reset();
        });

        renderizarReservas();
    }
}
