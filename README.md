<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0F172A,100:1D4ED8&height=210&section=header&text=Copa%20Interclasse%20Handebol&fontColor=FFFFFF&fontSize=40&fontAlignY=40&desc=Sistema%20Web%20para%20Campeonato%20Escolar%20em%20PHP%208%20e%20MySQL&descSize=15&descAlignY=62" width="100%" alt="Banner Copa Interclasse Handebol" />

<p>
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8" />
  <img src="https://img.shields.io/badge/MySQL-PDO-003545?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL com PDO" />
  <img src="https://img.shields.io/badge/Bootstrap-5-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5" />
  <img src="https://img.shields.io/badge/Arquitetura-MVC%20%2B%20POO-0F172A?style=for-the-badge" alt="Arquitetura MVC e POO" />
  <img src="https://img.shields.io/badge/Ambiente-XAMPP-FB7A24?style=for-the-badge" alt="Ambiente XAMPP" />
</p>

<p>
  <a href="#sobre-o-projeto">Visão Geral</a> &bull;
  <a href="#pré-visualização">Pré-visualização</a> &bull;
  <a href="#arquitetura-do-sistema">Arquitetura</a> &bull;
  <a href="#perfis-e-páginas">Perfis e Páginas</a> &bull;
  <a href="#regras-e-segurança">Regras e Segurança</a> &bull;
  <a href="#instalação-no-xampp">Instalação</a> &bull;
  <a href="#usuários-de-teste">Credenciais</a> &bull;
  <a href="#checklist-de-entrega-e-testes">Testes</a>
</p>

</div>

---

## Sobre o Projeto

O **Copa Interclasse Handebol** é uma aplicação web desenvolvida em **PHP 8 puro** com **MySQL/MariaDB**, estruturada sob o padrão arquitetural **MVC (Model-View-Controller)** e **Programação Orientada a Objetos (POO)**, para substituir planilhas isoladas na gestão anual do campeonato escolar de handebol (partidas em dois tempos de 20 minutos). O sistema preserva edições encerradas em modo histórico somente leitura, calcula a tabela de grupos automaticamente, registra a súmula eletrônica lance a lance e gera o chaveamento eliminatório até a definição de campeão e vice.

- **Placar derivado de eventos** — Não existe campo estático de placar no banco; a pontuação de cada jogo é obtida dinamicamente via `COUNT` dos gols lançados na súmula eletrônica (`RN03`).
- **Desqualificação automática na 3ª exclusão** — Ao gravar a terceira exclusão de 2 minutos para o mesmo atleta na partida, o servidor registra automaticamente o cartão vermelho e bloqueia novos eventos para aquele jogador (`RN09`).
- **Formatos configuráveis por edição** — O organizador escolhe entre **Grupos + Mata-mata** (6 a 8 equipes em dois grupos) ou **Mata-mata direto** (4 ou 8 equipes sorteadas), com decisão nos tiros de 7 metros em caso de empate eliminatório (`RN11`, `RE04`, `RE05`).
- **Histórico anual imutável** — Cada ano representa uma edição isolada; após o encerramento da final, campeão e vice são gravados e toda a edição passa a ser consultada via parâmetro `?ano=2025` sem possibilidade de edição (`RE01`, `RE02`, `RE07`).

> [!IMPORTANT]
> Apenas partidas com status `encerrada` compõem a tabela de classificação (`VIEW vw_classificacao`), o chaveamento do mata-mata e o ranking de artilharia (`RN04`). Disputas de tiros de 7 metros definem o vencedor em empates eliminatórios, mas não somam gols na artilharia nem no saldo de gols (`RN11`).

---

## Pré-visualização

Substitua os marcadores abaixo pelas capturas de tela reais obtidas no ambiente local (recomendado salvar em `docs/screenshots/`).

| Painel Público e Classificação (`index.php` / `classificacao.php`) | Chaveamento do Mata-mata (`chave.php`) |
| :---: | :---: |
| *Inserir captura de tela da página inicial e tabela dos grupos A e B* | *Inserir captura de tela das semifinais, final e tiros de 7 metros* |
| **Súmula Eletrônica em 1 Clique (`mesario/sumula.php`)** | **Painel Administrativo (`admin/painel.php`)** |
| *Inserir captura de tela do lançamento rápido e exclusões de 2 min* | *Inserir captura de tela dos indicadores da edição em andamento* |

---

## Stack Tecnológica

<div align="center">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://skillicons.dev/icons?i=php,mysql,bootstrap,html,css,js,git,github,vscode&theme=dark&perline=9" />
  <source media="(prefers-color-scheme: light)" srcset="https://skillicons.dev/icons?i=php,mysql,bootstrap,html,css,js,git,github,vscode&theme=light&perline=9" />
  <img src="https://skillicons.dev/icons?i=php,mysql,bootstrap,html,css,js,git,github,vscode&theme=dark&perline=9" alt="Stack tecnológica: PHP, MySQL, Bootstrap, HTML, CSS, JavaScript, Git, GitHub e VS Code" />
</picture>

</div>

| Camada | Tecnologia | Aplicação no Sistema |
| :--- | :--- | :--- |
| **Back-end** | PHP 8 Puro (POO + MVC) | Regras de negócio nos Controllers, persistência nos Models e controle de fluxo sem frameworks externos |
| **Banco de Dados** | MySQL / MariaDB via PDO | Conexão Singleton em `core/Database.php`, Prepared Statements, chaves estrangeiras com `ON DELETE RESTRICT` e `VIEW vw_classificacao` |
| **Front-end** | HTML5, CSS3, JavaScript e Bootstrap 5 | Interface responsiva via CDN do Bootstrap 5 compartilhada em `includes/header.php` e interatividade na súmula |
| **Infraestrutura** | XAMPP (Apache + MySQL + phpMyAdmin) | Servidor local padrão para execução, importação de `sql/banco.sql` e bateria de testes |

---

## Arquitetura do Sistema

A estrutura de diretórios separa infraestrutura central, camadas MVC, templates compartilhados e áreas restritas por perfil de acesso.

| Diretório / Arquivo | Camada | Responsabilidade Principal |
| :--- | :--- | :--- |
| `core/Database.php` | Core / Persistência | Conexão única com o MySQL utilizando PDO (padrão Singleton) e tratamento seguro de exceções |
| `core/AuthMiddleware.php` | Core / Segurança | Validação estrita de sessão ativa e controle de acesso por papel (`Visitante`, `Mesário`, `Organizador`) |
| `models/` | Model (MVC) | Consultas SQL parametrizadas (Prepared Statements) e leitura da `VIEW vw_classificacao` para cada entidade |
| `controllers/` | Controller (MVC) | Validações server-side (`RN01`–`RN12`, `RE01`–`RE07`) e processamento de formulários `POST` |
| `includes/` | Templates e Proteção | `header.php` (menu público, seletor `?ano=` e Bootstrap 5), `footer.php`, `protege.php` (e `conexao.php`, se aplicável) |
| `admin/` | Views do Organizador | Painel gerencial, CRUDs de edições, equipes, jogadores, partidas, mesários e geração de fases (7 páginas) |
| `mesario/` | Views do Mesário | Lista de jogos atribuídos (`painel.php`) e súmula eletrônica com lançamento rápido (`sumula.php`) |
| `css/` e `js/` | Recursos Estáticos | Folhas de estilo customizadas e scripts auxiliares de interface |
| `sql/banco.sql` | Banco de Dados | Script único com `CREATE DATABASE`, tabelas, `UNIQUE`, `ENUM`s, `VIEW vw_classificacao` e massa de testes (2025 e 2026) |
| `docs/` | Documentação | Artefatos obrigatórios: `der.png`, `dicionario.pdf`, `atividades-gerar-fase.png` e `wireframes.pdf` |
| `*.php` (raiz) | Views Públicas e Sessão | 8 páginas públicas de consulta esportiva, `login.php` e `logout.php` |

---

## Perfis e Páginas

O sistema possui 19 páginas distribuídas entre 3 perfis de acesso: **Visitante** (9 páginas públicas, sendo 8 de consulta e o login), **Mesário** (2 páginas) e **Organizador** (7 páginas), além do encerramento de sessão (`logout.php`).

| Perfil | Páginas | Escopo Funcional |
| :--- | :--- | :--- |
| <img src="https://api.iconify.design/lucide/home.svg?color=%233B82F6" width="16" height="16" alt="" /> **Visitante (Público)** | `index.php`, `classificacao.php`, `chave.php`, `jogos.php`, `partida.php?id=`, `artilharia.php`, `equipe.php?id=`, `historico.php` | Consulta da edição ativa ou de anos encerrados (`?ano=2025`), tabelas de grupos, chaveamento, detalhes da súmula, artilharia e campanha das equipes |
| <img src="https://api.iconify.design/ri/user-fill.svg?color=%2310B981" width="16" height="16" alt="" /> **Mesário** | `mesario/painel.php`, `mesario/sumula.php?id=` | Acesso restrito às partidas atribuídas ao próprio usuário: início do jogo (`7+` atletas por equipe), lances em 1 clique, tiros de 7 metros e encerramento |
| <img src="https://api.iconify.design/lucide/settings.svg?color=%23F59E0B" width="16" height="16" alt="" /> **Organizador** | `admin/painel.php`, `admin/edicoes.php`, `admin/equipes.php`, `admin/jogadores.php`, `admin/partidas.php`, `admin/fases.php`, `admin/usuarios.php` | Métricas da edição atual, gestão de edições e formatos, CRUDs de equipes, atletas e mesários, agenda sem conflito de quadra, reabertura de súmula e geração de fases |
| <img src="https://api.iconify.design/mdi/account.svg?color=%2364748B" width="16" height="16" alt="" /> **Autenticação** | `login.php`, `logout.php` | Autenticação por e-mail e senha (`password_verify`), redirecionamento por perfil e destruição segura da sessão |

---

## Regras e Segurança

### Ciclo de Vida de Partidas e Edições

| Entidade | Estados Permitidos | Condições de Transição |
| :--- | :--- | :--- |
| **Partida** | `agendada` &rarr; `em andamento` &rarr; `encerrada` | Início exige ao menos 7 jogadores aptos por equipe (`RN05`); empate no mata-mata exige placar de tiros de 7 metros sem novo empate (`RN11`); reabertura exclusiva do organizador antes da geração da fase seguinte (`RN10`) |
| **Edição** | `em andamento` &rarr; `encerrada` | Apenas uma edição ativa por vez (`RE02`); encerrada a final, campeão e vice são gravados e toda a edição torna-se somente leitura (`RE02`, `RE07`) |

<details>
<summary><strong>Matriz Completa de Regras de Negócio (RN01–RN12 e RE01–RE07)</strong></summary>

<br />

| Código | Regra Aplicada | Garantia no Sistema |
| :--- | :--- | :--- |
| **RN01 / RN02** | Vitória vale 2 pontos, empate 1 e derrota 0; desempate por Pontos, Saldo de Gols (`SG`), Gols Marcados (`GP`) e Nome da equipe | `VIEW vw_classificacao` com `UNION ALL` e `ORDER BY` |
| **RN03 / RN04** | Placar calculado por `COUNT` dos gols na súmula; apenas partidas encerradas entram na classificação, na chave e na artilharia | Consultas agregadas e filtro `WHERE` na `VIEW` |
| **RN05 / RN06** | Equipe possui de 7 a 14 jogadores; camisa única na equipe e RM único por edição | Restrições `UNIQUE` no banco e validação no Controller PHP |
| **RN07** | Equipe não enfrenta a si mesma; quadra não recebe dois jogos na mesma data e horário | `UNIQUE` (quadra, data, horário) e validação PHP |
| **RN08 / RN09** | Eventos válidos somente para atletas da partida entre os minutos 1 e 40; 3ª exclusão grava cartão vermelho automático e bloqueia novos eventos do jogador | Validação server-side antes de gravar evento (`sumula.php` / Controller) |
| **RN10 / RN11** | Mesário acessa apenas partidas atribuídas a ele; empate no mata-mata vai aos tiros de 7 metros (sem empate e fora da artilharia/saldo) | `AuthMiddleware` / sessão e regras no encerramento |
| **RN12** | Equipe ou jogador com registro em partida não é excluído; o sistema oferece inativação | `FOREIGN KEY ... ON DELETE RESTRICT` + PHP |
| **RE01 a RE03** | Ano único por edição; só uma edição em andamento por vez; formato editável apenas antes da 1ª partida iniciar | `UNIQUE` em `edicoes.ano` + Controller PHP |
| **RE04 a RE07** | Grupos + mata-mata (6–8 equipes em 2 grupos com diferença máxima de 1 equipe, cruzamento `1º A x 2º B` e `1º B x 2º A`); Mata-mata direto (4 ou 8 equipes sorteadas); próxima fase exige todas as partidas atuais encerradas | Controller de Fases (`admin/fases.php`) |

</details>

### Requisitos Obrigatórios de Segurança

1. **Armazenamento de senhas** — Senhas gravadas exclusivamente com `password_hash()` (hash bcrypt iniciado por `$2y$`) e validadas com `password_verify()`.
2. **Consultas parametrizadas** — Todas as operações SQL com entrada de usuário utilizam PDO com Prepared Statements via `core/Database.php`.
3. **Proteção de sessão e rotas** — Páginas restritas validam sessão e perfil no topo da execução (`core/AuthMiddleware.php` / `includes/protege.php`), regeneram o ID após o login (`session_regenerate_id(true)`) e destroem a sessão no `logout.php`.
4. **Prevenção contra XSS e alterações via GET** — Saídas renderizadas passam por `htmlspecialchars()` e todas as mutações de dados utilizam requisições `POST`.
5. **Isolamento de erros** — Mensagens técnicas de erro do PHP ou do MySQL não são exibidas ao usuário final.

---

## Instalação no XAMPP

### Pré-requisitos

- **XAMPP** instalado com **Apache**, **PHP 8.x** e **MySQL / MariaDB** em execução.
- **Git** (opcional para clonagem) e navegador web atualizado.

### Passo a Passo

1. Clone ou extraia o repositório diretamente dentro do diretório `htdocs` do XAMPP:

```bash
cd C:\xampp\htdocs
git clone https://github.com/<seu-usuario>/<seu-repositorio>.git copa-interclasse-handebol
```

2. Abra o **XAMPP Control Panel** e inicie os módulos **Apache** e **MySQL**.
3. Acesse o **phpMyAdmin** em `http://localhost/phpmyadmin`.
4. Na aba **Importar**, selecione o arquivo `sql/banco.sql` e execute a importação para criar o banco de dados, as tabelas, a `VIEW vw_classificacao` e as duas edições de teste (2025 e 2026).
5. Confira os parâmetros de conexão com o banco (host, nome do banco, usuário e senha) em `core/Database.php` (e em `includes/conexao.php`, se mantido) para assegurar compatibilidade com a sua instância local do MySQL no XAMPP.
6. Abra a aplicação no navegador:

```text
http://localhost/copa-interclasse-handebol/
```

> [!WARNING]
> O script `sql/banco.sql` executa `CREATE DATABASE` e insere a massa inicial de dados de 2025 (encerrada em mata-mata direto com 4 equipes) e 2026 (em andamento em grupos + mata-mata com 6 equipes e fase de grupos encerrada). Caso exista um banco homônimo no XAMPP local, realize backup antes da importação.

---

## Usuários de Teste

Preencha os campos abaixo exatamente com os usuários inseridos pelo script `sql/banco.sql` para validação dos perfis **Organizador** e **Mesário**.

| Perfil | Nome / Referência | E-mail de Acesso | Senha em Texto Plano (para Login) |
| :--- | :--- | :--- | :--- |
| **Organizador** | `[Preencher conforme sql/banco.sql]` | `[organizador@exemplo.com]` | `[definir_senha_do_seed]` |
| **Mesário 1** | `[Preencher conforme sql/banco.sql]` | `[mesario1@exemplo.com]` | `[definir_senha_do_seed]` |
| **Mesário 2** | `[Preencher conforme sql/banco.sql]` | `[mesario2@exemplo.com]` | `[definir_senha_do_seed]` |

> [!TIP]
> As páginas públicas (`index.php`, `classificacao.php`, `chave.php`, `jogos.php`, `artilharia.php` e `historico.php`) não requerem login. Para inspecionar a edição encerrada de 2025, adicione `?ano=2025` à URL ou selecione o ano no menu superior.

---

## Checklist de Entrega e Testes

### Status das Entregas

Atualize a coluna **Status** antes da apresentação final no 15º dia, registrando de forma objetiva o que foi finalizado ou permaneceu pendente.

| Entregável / Módulo | Status | Observações |
| :--- | :--- | :--- |
| Documentação em `docs/` (`der.png`, `dicionario.pdf`, `atividades-gerar-fase.png`, `wireframes.pdf`) | `[Pronto / Pendente]` | Diagramas e especificações coerentes com o código |
| Banco `sql/banco.sql` (`VIEW vw_classificacao` e massa de dados 2025/2026) | `[Pronto / Pendente]` | Importação limpa validada no phpMyAdmin |
| Área pública (8 páginas de consulta esportiva e filtro `?ano=`) | `[Pronto / Pendente]` | Classificação, chaveamento, jogos, partida, artilharia, equipe e histórico |
| Autenticação e autorização (`login.php`, `AuthMiddleware`, `logout.php`) | `[Pronto / Pendente]` | Hash bcrypt `$2y$` e bloqueio por perfil via URL |
| Área do Organizador (`admin/*.php`, CRUDs, agendamento e fases) | `[Pronto / Pendente]` | Validações `RN05`–`RN07`, `RN12` e `RE01`–`RE07` |
| <img src="https://api.iconify.design/material-symbols/bolt.svg?color=%23F59E0B" width="16" height="16" alt="" /> Súmula Eletrônica (`mesario/sumula.php`, 3ª exclusão e 7 metros) | `[Pronto / Pendente]` | Lançamento em 1 clique e placar calculado via `COUNT` |
| <img src="https://api.iconify.design/tabler/star.svg?color=%233B82F6" width="16" height="16" alt="" /> Desafios Bônus (suspensão / bye / 3º lugar / avanço automático / disciplina / desfazer em 1 clique) | `[Não implementado / Listar implementados]` | Informar apenas recursos testados após validar 100% dos obrigatórios |

<details>
<summary><strong>Cenários de Conferência (T01–T19) e Tabela Esperada (T18)</strong></summary>

<br />

Resultado esperado no cenário de conferência `T18` (grupo com 3 equipes e partidas `Equipe A 5 x 3 Equipe B`, `Equipe B 4 x 4 Equipe C` e `Equipe C 2 x 3 Equipe A`):

| Pos. | Equipe | J | V | E | D | GP | GC | SG | P |
| :---: | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 1 | Equipe A | 2 | 2 | 0 | 0 | 8 | 5 | 3 | 4 |
| 2 | Equipe C | 2 | 0 | 1 | 1 | 6 | 7 | -1 | 1 |
| 3 | Equipe B | 2 | 0 | 1 | 1 | 7 | 9 | -2 | 1 |

Cobertura da suíte de testes antes da entrega:
- `T01` a `T03`: Redirecionamento para login sem sessão, bloqueio de mesário em rota administrativa e bloqueio em súmula de outro mesário.
- `T04` e `T05`: Recusa de SQL Injection no login (`' OR '1'='1`) sem erro SQL visível e neutralização de XSS (`<script>alert(1)</script>`) via `htmlspecialchars()`.
- `T06` a `T09`: Bloqueio de conflito de quadra/horário, bloqueio com menos de 7 jogadores, rejeição server-side de jogador adulterado no DevTools e desqualificação automática na 3ª exclusão.
- `T10` a `T19`: Unicidade de ano/edição ativa, bloqueio de edição encerrada (2025), restrição de sorteio a 4 ou 8 equipes, bloqueio de geração de fase com jogo aberto, validação de tiros de 7 metros em empates eliminatórios, registro de campeão/vice na final e confirmação de hash `$2y$` no banco.

</details>

---

## Equipe e Documentação

### Integrantes

| Integrante | Responsabilidade Principal | GitHub |
| :--- | :--- | :--- |
| `[Nome Completo 1]` | `[Ex.: Core MVC, Banco de Dados e Segurança]` | `[@usuario1](https://github.com/usuario1)` |
| `[Nome Completo 2]` | `[Ex.: Área Pública, Classificação e Chaveamento]` | `[@usuario2](https://github.com/usuario2)` |
| `[Nome Completo 3]` | `[Ex.: Painel Admin, Súmula Eletrônica e Testes]` | `[@usuario3](https://github.com/usuario3)` |

### Artefatos Complementares

- <img src="https://api.iconify.design/lucide/external-link.svg?color=%2364748B" width="14" height="14" alt="" /> [Diagrama Entidade-Relacionamento (DER)](docs/der.png)
- <img src="https://api.iconify.design/lucide/external-link.svg?color=%2364748B" width="14" height="14" alt="" /> [Dicionário de Dados](docs/dicionario.pdf)
- <img src="https://api.iconify.design/lucide/external-link.svg?color=%2364748B" width="14" height="14" alt="" /> [Diagrama de Atividades — Gerar Próxima Fase](docs/atividades-gerar-fase.png)
- <img src="https://api.iconify.design/lucide/external-link.svg?color=%2364748B" width="14" height="14" alt="" /> [Wireframes das Telas](docs/wireframes.pdf)

<div align="center">

<br />
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0F172A,100:1D4ED8&height=110&section=footer" width="100%" alt="Rodapé Copa Interclasse Handebol" />

</div>