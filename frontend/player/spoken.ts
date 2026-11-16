// English money and number shorthand, the way people say it: "$12.7bn" → "12.7 billion dollars",
// "3–5" → "3 to 5". Same rules as backend/Player/Spoken.php; keep the two in step.

const CURRENCIES: Record<string, string> = { $: 'dollars', '£': 'pounds', '€': 'euros', '¥': 'yen', '₹': 'rupees' }
const MAGNITUDES: Record<string, string> = {
  k: 'thousand',
  thousand: 'thousand',
  m: 'million',
  mn: 'million',
  million: 'million',
  b: 'billion',
  bn: 'billion',
  billion: 'billion',
  t: 'trillion',
  tn: 'trillion',
  trillion: 'trillion',
}
const NUM = String.raw`\d[\d,]*(?:\.\d+)?`

/** English only for now. */
export const spokenApplies = (lang: string | undefined) => (lang ?? '').toLowerCase().startsWith('en')

export function spokenNumbers(text: string): string {
  if (!/\d/.test(text)) return text
  let out = text
    .replace(new RegExp(String.raw`(?<![\d.,])(${NUM})\s?[–—]\s?(?=[$£€¥₹]?\d)`, 'gu'), '$1 to ')
    .replace(/(?<![\d-])((?:1[89]|20)\d{2})-(\d{2}|(?:1[89]|20)\d{2})(?![\d-])/g, '$1 to $2')
  out = out.replace(
    new RegExp(String.raw`([$£€¥₹])\s?(${NUM})(?:([kKmMbBtT]|mn|bn|tn)\b|\s([Tt]housand|[Mm]illion|[Bb]illion|[Tt]rillion)\b)`, 'gu'),
    (_m, cur: string, amount: string, short?: string, long?: string) => `${amount} ${MAGNITUDES[(short || long || '').toLowerCase()]} ${CURRENCIES[cur]}`,
  )
  return out.replace(new RegExp(String.raw`(?<![\w.])(${NUM})(bn|tn)\b`, 'g'), (_m, amount: string, size: string) => `${amount} ${MAGNITUDES[size]}`)
}
