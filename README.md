# Naruto RPG

Sistema de fichas de RPG ambientado no universo de Naruto, construído em Laravel.
Permite criar e editar fichas de personagem (com autosave), organizar campanhas
com convite por link, e gerenciar os participantes — incluindo gestão de fichas
pelo mestre e banimento de membros.

## Stack

- **PHP** 8.3+ / **Laravel** 13
- **Livewire** 4 (ficha de personagem reativa, com autosave)
- **Breeze** (autenticação)
- **Tailwind CSS** 3 + **Alpine.js** + **Vite**
- **MariaDB** (via [ddev](https://ddev.com))
- **Laravel Dusk** (testes de browser, em banco SQLite separado)

## Pré-requisitos

- [ddev](https://ddev.com/get-started/) (Docker)

> O ddev já provê PHP, MariaDB, Node e Composer dentro do container, então não é
> necessário instalá-los na máquina. Todos os comandos abaixo podem ser rodados
> com o prefixo `ddev` (ex.: `ddev composer`, `ddev artisan`, `ddev npm`).

## Como rodar

```bash
# 1. Subir os containers
ddev start

# 2. Instalar dependências PHP
ddev composer install

# 3. Criar o .env e gerar a APP_KEY
cp .env.example .env
ddev artisan key:generate

# 4. Rodar as migrations
ddev artisan migrate

# 5. Instalar dependências JS e compilar os assets
ddev npm install
ddev npm run build
```

Depois disso, abra a aplicação:

```bash
ddev launch
```

A URL local é exibida por `ddev describe` (normalmente `https://naruto-rpg.ddev.site`).

### Desenvolvimento (assets em watch)

Para desenvolver com recompilação automática do front-end, rode o Vite em modo dev:

```bash
ddev npm run dev
```

## Banco de dados

- O ambiente usa o **MariaDB do container `db` do ddev**; os dados ficam no volume
  do ddev e **persistem** entre reinícios. As credenciais já estão no `.env.example`
  (`DB_HOST=db`, `DB_DATABASE=db`, `DB_USERNAME=db`, `DB_PASSWORD=db`).
- Para recriar o schema do zero: `ddev artisan migrate:fresh`.

> **Atenção:** não há seeders que criem usuários automaticamente. Cadastre sua
> conta pela própria tela de registro da aplicação.

## Testes

Testes unitários e de feature (PHPUnit):

```bash
ddev artisan test
```

Testes de browser (Dusk) — rodam em um **banco SQLite separado**, configurado em
`.env.dusk.local`, para não apagar os dados de desenvolvimento:

```bash
ddev artisan dusk
```

## Estilo de código

O projeto usa [Laravel Pint](https://laravel.com/docs/pint):

```bash
ddev composer exec pint
```

## Principais funcionalidades

- **Fichas de personagem** — criação e edição reativa (Livewire) com autosave;
  seção de Perícias com trava (cadeado) para evitar alterações acidentais.
- **Campanhas** — criação, convite por link/token (com regeneração), e visualização
  dos participantes e suas fichas.
- **Gestão pelo mestre** — remoção de fichas da campanha e banimento de membros.
