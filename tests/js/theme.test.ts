import { afterEach, describe, expect, it } from 'vitest'
import { applyTheme, luminance, pageIsDark, watchTheme } from '../../frontend/player/theme'

const setup = (bg: string) => {
  document.body.innerHTML = `<main style="background-color: ${bg}"><div class="listenly-player"></div></main>`
  return document.querySelector<HTMLElement>('.listenly-player')!
}

afterEach(() => {
  document.body.innerHTML = ''
  document.documentElement.className = ''
  document.body.removeAttribute('style')
})

describe('player colours', () => {
  it('measures colours and ignores see-through ones', () => {
    expect(luminance('rgb(255, 255, 255)')).toBeCloseTo(1)
    expect(luminance('rgb(0, 0, 0)')).toBe(0)
    expect(luminance('rgba(0, 0, 0, 0)')).toBeNull()
    expect(luminance('transparent')).toBeNull()
  })

  it('reads the first solid background behind the player', () => {
    expect(pageIsDark(setup('rgb(18, 18, 18)'))).toBe(true)
    expect(pageIsDark(setup('rgb(250, 250, 249)'))).toBe(false)
  })

  it('treats a page with no background as light', () => {
    document.body.innerHTML = '<div class="listenly-player"></div>'
    expect(pageIsDark(document.querySelector<HTMLElement>('.listenly-player')!)).toBe(false)
  })

  it('auto switches the player and the read-along highlight to dark on a dark page', () => {
    const root = setup('rgb(18, 18, 18)')
    watchTheme(root, 'auto')
    expect(root).toHaveClass('listenly-player--dark')
    expect(document.documentElement).toHaveClass('listenly-dark')
  })

  it('follows a theme that switches to dark later', async () => {
    document.body.innerHTML = '<div class="listenly-player"></div>'
    const root = document.querySelector<HTMLElement>('.listenly-player')!
    watchTheme(root, 'auto')
    expect(root).not.toHaveClass('listenly-player--dark')
    document.body.style.backgroundColor = 'rgb(10, 10, 10)'
    await new Promise((r) => setTimeout(r, 0))
    expect(root).toHaveClass('listenly-player--dark')
  })

  it('keeps a forced choice whatever the page looks like', () => {
    const root = setup('rgb(18, 18, 18)')
    watchTheme(root, 'light')
    expect(root).not.toHaveClass('listenly-player--dark')
    const light = setup('rgb(255, 255, 255)')
    watchTheme(light, 'dark')
    expect(light).toHaveClass('listenly-player--dark')
  })

  it('removes dark again', () => {
    const root = setup('rgb(255, 255, 255)')
    applyTheme(root, true)
    applyTheme(root, false)
    expect(root).not.toHaveClass('listenly-player--dark')
    expect(document.documentElement).not.toHaveClass('listenly-dark')
  })

  it('settles when two players on one page disagree', async () => {
    document.body.innerHTML = '<main style="background-color: rgb(10, 10, 10)"><div class="a"></div></main><aside style="background-color: rgb(255, 255, 255)"><div class="b"></div></aside>'
    const a = document.querySelector<HTMLElement>('.a')!
    const b = document.querySelector<HTMLElement>('.b')!
    watchTheme(a, 'auto')
    watchTheme(b, 'auto')
    document.body.style.color = 'red'
    await new Promise((r) => setTimeout(r, 20))
    expect(a).toHaveClass('listenly-player--dark')
    expect(b).not.toHaveClass('listenly-player--dark')
  })
})
