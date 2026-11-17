import { createRoot } from 'react-dom/client'
import '@fontsource/geist/400.css'
import '@fontsource/geist/500.css'
import '@fontsource/geist/600.css'
import './styles.css'
import { App } from './App'

const mount = () => {
  const el = document.getElementById('postaloud-app')
  if (el) createRoot(el).render(<App />)
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount)
else mount()
