# WhatsApp Bot Abiplanung Frontend

This is the Vue 3 + TypeScript + Tailwind frontend for the WhatsApp Bot Abiplanung project. It connects to the Laravel backend API to display and manage WhatsApp messages.

## Getting Started

### 1. Install dependencies
```bash
npm install
```

### 2. Environment setup
- If you need to change the API base URL, create a `.env` file and set `VITE_API_BASE_URL` (see Vite docs).
- By default, the frontend expects the backend to be running on `http://localhost:8000` and proxies `/api` and `/storage` requests via Vite config.

### 3. Run the development server
```bash
npm run dev
```

### 4. Lint and format code
```bash
npm run lint    # ESLint
npm run format  # Prettier
```

### 5. Run unit tests
```bash
npm run test:unit
```

## API Usage
- The frontend fetches messages from the backend at `/api/messages`.
- See the backend README for more API details.

---

# vue-project

This template should help get you started developing with Vue 3 in Vite.

## Recommended IDE Setup

[VSCode](https://code.visualstudio.com/) + [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Type Support for `.vue` Imports in TS

TypeScript cannot handle type information for `.vue` imports by default, so we replace the `tsc` CLI with `vue-tsc` for type checking. In editors, we need [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) to make the TypeScript language service aware of `.vue` types.

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Type-Check, Compile and Minify for Production

```sh
npm run build
```

### Run Unit Tests with [Vitest](https://vitest.dev/)

```sh
npm run test:unit
```

### Lint with [ESLint](https://eslint.org/)

```sh
npm run lint
```
