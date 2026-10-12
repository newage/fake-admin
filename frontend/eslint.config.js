import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  { ignores: ['dist', 'node_modules'] },
  {
    files: ['**/*.{ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: { globals: globals.browser },
    plugins: { 'react-hooks': reactHooks },
    rules: {
      ...reactHooks.configs.recommended.rules,
      // React Compiler is not used; its warning about TanStack Table does not apply.
      'react-hooks/incompatible-library': 'off',
      // The refresh token lives in localStorage: we do not widen the XSS surface (see design.md, "Session").
      'no-restricted-syntax': [
        'error',
        {
          selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
          message: 'dangerouslySetInnerHTML заборонено: refresh-токен у localStorage.',
        },
      ],
      'no-restricted-properties': [
        'error',
        { property: 'innerHTML', message: 'innerHTML заборонено: refresh-токен у localStorage.' },
        { property: 'outerHTML', message: 'outerHTML заборонено: refresh-токен у localStorage.' },
      ],
    },
  },
)
