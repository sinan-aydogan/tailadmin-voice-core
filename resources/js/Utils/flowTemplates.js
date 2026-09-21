// Starter flow definitions for common voice-agent channels. Each `definition`
// uses the storage node shape ({id, type, position, data}) consumed directly
// by FlowController::store() and understood by Builder.vue's toCanvasNode().
// Placeholder tokens (YOUR_...) must be edited by the user after creation.

function n(id, type, x, y, data) {
  return { id, type, position: { x, y }, data }
}

function e(id, source, target, sourceHandle = null) {
  return { id, source, target, sourceHandle }
}

export const FLOW_TEMPLATES = [
  {
    key: 'telegram',
    name: 'Telegram Sesli Bot',
    icon: '✈️',
    description: 'Gelen sesli mesajı yazıya çevirir, LLM ile cevap üretir, sesli olarak Telegram\'a geri gönderir. Hafıza açık (chat.id ile).',
    definition: {
      nodes: [
        n('trigger', 'trigger.webhook', 40, 220, {}),
        n('get_file', 'action.http', 340, 60, {
          method: 'GET',
          url: 'https://api.telegram.org/botYOUR_TELEGRAM_BOT_TOKEN/getFile?file_id={{trigger.body.message.voice.file_id}}',
          headers: [], body: [], timeout: 30,
        }),
        n('stt', 'action.stt', 660, 60, {
          audio_source: 'https://api.telegram.org/file/botYOUR_TELEGRAM_BOT_TOKEN/{{get_file.body.result.file_path}}',
          language: 'tr', model_size: '',
        }),
        n('llm', 'action.llm', 980, 220, {
          prompt: '{{stt.text}}',
          system_prompt: 'Sen Telegram üzerinden sesli mesajlaşan, kısa ve doğal cevaplar veren yardımsever bir asistansın.',
          provider: '', model: '', prompt_template_id: null, template_values: {},
          use_memory: true, memory_turns: 6, conversation_id: '{{trigger.body.message.chat.id}}',
          use_knowledge: false,
        }),
        n('tts', 'action.tts', 1300, 220, { text: '{{llm.text}}', engine: 'piper-tr', language: 'tr', profile_id: null }),
        n('send_voice', 'action.http', 1620, 220, {
          method: 'POST',
          url: 'https://api.telegram.org/botYOUR_TELEGRAM_BOT_TOKEN/sendVoice',
          headers: [],
          body: [
            { key: 'chat_id', value: '{{trigger.body.message.chat.id}}' },
            { key: 'voice', value: '{{tts.audio_url}}' },
          ],
          timeout: 30,
        }),
        n('response', 'output.response', 1940, 220, { mode: 'json', source: '{{send_voice.body}}' }),
      ],
      edges: [
        e('e1', 'trigger', 'get_file'),
        e('e2', 'get_file', 'stt'),
        e('e3', 'stt', 'llm'),
        e('e4', 'llm', 'tts'),
        e('e5', 'tts', 'send_voice'),
        e('e6', 'send_voice', 'response'),
      ],
    },
  },
  {
    key: 'whatsapp',
    name: 'WhatsApp Sesli Bot',
    icon: '💬',
    description: 'Meta WhatsApp Cloud API ile sesli mesaj alır, LLM ile cevap üretir, sesi bağlantı (link) olarak geri gönderir. Hafıza açık (gönderen numara ile).',
    definition: {
      nodes: [
        n('trigger', 'trigger.webhook', 40, 220, {}),
        n('get_media', 'action.http', 340, 60, {
          method: 'GET',
          url: 'https://graph.facebook.com/v20.0/{{trigger.body.entry.0.changes.0.value.messages.0.audio.id}}',
          headers: [{ key: 'Authorization', value: 'Bearer YOUR_WHATSAPP_ACCESS_TOKEN' }],
          body: [], timeout: 30,
        }),
        n('stt', 'action.stt', 660, 60, { audio_source: '{{get_media.body.url}}', language: 'tr', model_size: '' }),
        n('llm', 'action.llm', 980, 220, {
          prompt: '{{stt.text}}',
          system_prompt: 'Sen WhatsApp üzerinden sesli mesajlaşan, kısa ve doğal cevaplar veren yardımsever bir asistansın.',
          provider: '', model: '', prompt_template_id: null, template_values: {},
          use_memory: true, memory_turns: 6, conversation_id: '{{trigger.body.entry.0.changes.0.value.messages.0.from}}',
          use_knowledge: false,
        }),
        n('tts', 'action.tts', 1300, 220, { text: '{{llm.text}}', engine: 'piper-tr', language: 'tr', profile_id: null }),
        n('send_message', 'action.http', 1620, 220, {
          method: 'POST',
          url: 'https://graph.facebook.com/v20.0/YOUR_WHATSAPP_PHONE_NUMBER_ID/messages',
          headers: [{ key: 'Authorization', value: 'Bearer YOUR_WHATSAPP_ACCESS_TOKEN' }],
          body: [
            { key: 'messaging_product', value: 'whatsapp' },
            { key: 'to', value: '{{trigger.body.entry.0.changes.0.value.messages.0.from}}' },
            { key: 'type', value: 'audio' },
            { key: 'audio', value: '{"link":"{{tts.audio_url}}"}' },
          ],
          timeout: 30,
        }),
        n('response', 'output.response', 1940, 220, { mode: 'json', source: '{{send_message.body}}' }),
      ],
      edges: [
        e('e1', 'trigger', 'get_media'),
        e('e2', 'get_media', 'stt'),
        e('e3', 'stt', 'llm'),
        e('e4', 'llm', 'tts'),
        e('e5', 'tts', 'send_message'),
        e('e6', 'send_message', 'response'),
      ],
    },
  },
  {
    key: 'twilio',
    name: 'Twilio Sesli Yanıt (Telefon)',
    icon: '📞',
    description: 'Gerçek bir telefon görüşmesi: ilk aramada karşılar, sonraki turlarda Twilio\'nun kendi konuşma tanımasıyla gelen SpeechResult\'ı LLM\'e yollar. Hafıza CallSid ile.',
    definition: {
      nodes: [
        n('trigger', 'trigger.webhook', 40, 260, {}),
        n('has_speech', 'logic.condition', 340, 260, { left: '{{trigger.body.SpeechResult}}', operator: 'exists', right: '' }),

        // Branch: caller already said something → answer with LLM
        n('llm', 'action.llm', 660, 100, {
          prompt: '{{trigger.body.SpeechResult}}',
          system_prompt: 'Sen telefonla arayan kişiyle konuşan sesli bir müşteri temsilcisisin. Kısa, doğal ve telefon diline uygun cevaplar ver.',
          provider: '', model: '', prompt_template_id: null, template_values: {},
          use_memory: true, memory_turns: 8, conversation_id: '{{trigger.body.CallSid}}',
          use_knowledge: false,
        }),
        n('tts_reply', 'action.tts', 980, 100, { text: '{{llm.text}}', engine: 'piper-tr', language: 'tr', profile_id: null }),
        n('response_reply', 'output.response', 1300, 100, { mode: 'twiml', twiml_type: 'play_and_gather', source: '{{tts_reply.audio_url}}' }),

        // Branch: first call, no speech yet → greet and start listening
        n('tts_greeting', 'action.tts', 660, 420, {
          text: 'Merhaba! Ben sesli asistanınızım, size nasıl yardımcı olabilirim?',
          engine: 'piper-tr', language: 'tr', profile_id: null,
        }),
        n('response_greeting', 'output.response', 980, 420, { mode: 'twiml', twiml_type: 'play_and_gather', source: '{{tts_greeting.audio_url}}' }),
      ],
      edges: [
        e('e1', 'trigger', 'has_speech'),
        e('e2', 'has_speech', 'llm', 'true'),
        e('e3', 'llm', 'tts_reply'),
        e('e4', 'tts_reply', 'response_reply'),
        e('e5', 'has_speech', 'tts_greeting', 'false'),
        e('e6', 'tts_greeting', 'response_greeting'),
      ],
    },
  },
]
