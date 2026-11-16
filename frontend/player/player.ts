// The frontend player entry. No React, no dependencies: it must stay small.
// Reads data-listenly (backend/Player/Player.php); see mount.ts.
import './player.css'
import { mount } from './mount'

const start = () => document.querySelectorAll<HTMLElement>('.listenly-player[data-listenly]').forEach(mount)
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
else start()

// Stop the browser voice when the visitor leaves the page.
window.addEventListener('pagehide', () => window.speechSynthesis?.cancel())
