/**
 * Voice Core Text Sanitizer & Voiceover Directives Parser
 * 
 * Separates voiceover / director instructions (Suno-style) from spoken text,
 * protects acoustic shortcodes ([sigh], [pause], [laughter], etc.),
 * and strips Markdown formatting, asterisks (**), headings (###), and emojis
 * so TTS engines (Piper, XTTS, Bark, Freya, etc.) don't pronounce symbols aloud.
 */

// Supported acoustic tags across all engines that should NEVER be stripped
const PROTECTED_SHORTCODE_REGEX = /\[(?:pause|sigh|laughter|laughs|deep breath|whisper|gasp|throat-clearing|clearing throat|yawn|groan)\]|<break\s+[^>]+>|<\/?emphasis[^>]*>|♪[^♪\n]+♪/gi

export function parseVoiceoverText(input) {
  let text = input || ''
  let directive = ''
  let hasNotes = false

  // 0. Shield acoustic shortcodes so markdown/stage cleaners do not touch them
  const protectedTokens = []
  text = text.replace(PROTECTED_SHORTCODE_REGEX, (match) => {
    const token = `__TTS_ACOUSTIC_${protectedTokens.length}__`
    protectedTokens.push({ token, value: match })
    return token
  })

  // 1. Extract primary voiceover / director notes:
  // e.g. **(Seslendirme Notu: ...)**, (Yönetmen Notu: ...), [Seslendirme Talimatı: ...]
  const noteRegex = /(?:\*{0,2})[\[\(](?:seslendirme\s*notu|yönetmen\s*notu|ton|tarz|talimat|ses\s*tonu|not)[:\-–\s]+([^\]\)]+)[\]\)](?:\*{0,2})/i
  const noteMatch = text.match(noteRegex)
  if (noteMatch) {
    directive = noteMatch[1].trim()
    hasNotes = true
    text = text.replace(noteMatch[0], ' ')
  }

  // 2. Remove Markdown headings with emojis (e.g. ### 🎤 Haber Spikeri Anons Metni)
  text = text.replace(/(?:^|\n)\s*#{1,6}\s+[^\n]+/g, ' ')
  text = text.replace(/#{1,6}\s+[^*\n]+(?=\*\*|\*|\n|$)/gi, ' ')
  text = text.replace(/#{1,6}\s+/g, ' ')

  // 3. Remove stage / section directions in parentheses or brackets:
  // e.g. **(Giriş – Enerjik ve Selamlayıcı)**, (Bülten – Dinamik ve Tarafsız Ton), (Kapanış – Güven Veren)
  const stageRegex = /(?:\*{0,2})[\[\(](?:giriş|gelişme|bülten|kapanış|anons|müzik|arka\s*plan|efekt|enerjik|dinamik|tarafsız|güven|selamlayıcı|seslendirme|spiker|not|talimat)[^\]\)]*[\]\)](?:\*{0,2})/gi
  if (stageRegex.test(text)) {
    hasNotes = true
    text = text.replace(stageRegex, ' ')
  }

  // Remove any remaining standalone bold parenthetical notes like **(Not: ...)**
  text = text.replace(/\*\*\([^)]+\)\*\*/g, ' ')
  text = text.replace(/\[\([^)]+\)\]/g, ' ')

  // 4. Remove horizontal rules & decorative dividers (***, ---, ___)
  text = text.replace(/[\*\-_]{3,}/g, ' ')

  // 5. Remove remaining Markdown inline formatting (bold, italic, code, strikethrough)
  text = text.replace(/\*\*([^*]+)\*\*/g, '$1')
  text = text.replace(/\*([^*]+)\*/g, '$1')
  text = text.replace(/__([^_]+)__/g, '$1')
  text = text.replace(/_([^_]+)_/g, '$1')
  text = text.replace(/`([^`]+)`/g, '$1')
  text = text.replace(/~~([^~]+)~~/g, '$1')

  // 6. Remove emojis that cause speech models to stutter or pronounce symbol names
  text = text.replace(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E0}-\u{1F1FF}]/gu, ' ')

  // 7. Clean residual orphaned asterisks, hashes, brackets (preserving square brackets for tokens)
  text = text.replace(/[*#~]/g, '')

  // 8. Restore protected acoustic shortcodes
  for (const item of protectedTokens) {
    text = text.replace(item.token, item.value)
  }

  // 9. Normalize multiple spaces and line breaks
  text = text.replace(/[ \t]+/g, ' ')
  text = text.replace(/\n\s*\n\s*\n+/g, '\n\n')
  text = text.trim()

  return {
    directive,
    cleanText: text,
    hasNotes,
    rawText: input || '',
  }
}

export function sanitizeTtsText(input) {
  return parseVoiceoverText(input).cleanText
}
