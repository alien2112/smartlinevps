# 🚗 SmartLine AI Chatbot V3.4.1

**Production-ready AI chatbot for SmartLine ride-hailing platform** with comprehensive support for both customers and captains.

---

## 🆕 V3.4.1 - What's New

### ✅ Critical Bug Fixes

| Bug | Problem | Fix |
|-----|---------|-----|
| **Travel Advice** | Bot said "take the metro" instead of booking | Dual-location detection BEFORE LLM call |
| **Too Many Questions** | Asked for date, passengers, vehicle all at once | One question at a time with quick replies |
| **Location Detection** | "من العجمي ل الجيزة" wasn't parsed | Comprehensive Arabic/English patterns |

### ✅ New Features

| Feature | Description |
|---------|-------------|
| 🎯 **Quick Replies Everywhere** | Customers tap buttons instead of typing |
| 👨‍💼 **Human Decision Making** | All strikes/bans require human approval |
| 📢 **Smart Notifications** | Back-office gets real-time alerts |
| 🛡️ **No Auto-Blocking** | Anti-fraud warns but never blocks customers |
| 📎 **Evidence Collection** | Files saved for human review |

---

## 🚀 Quick Start

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
```bash
cp .env.example .env
# Edit .env with your settings
```

### 3. Start Server
```bash
npm start
```

### 4. Test Critical Bugs
```bash
node test_critical_bugs.js
```

---

## 📋 Features

### Customer Features
- ✅ **Trip Booking** - Complete booking flow with quick replies
- ✅ **Smart Location Detection** - "من X ل Y" pattern auto-detected
- ✅ **One Question at a Time** - Simple, fast booking
- ✅ **Trip Management** - Track, cancel, rate trips
- ✅ **Issue Reporting** - Report problems with quick buttons
- ✅ **Multi-language** - Arabic, English, Arabizi

### Captain Features
- ✅ **Registration Status Check** - Check approval status
- ✅ **Multi-language Support** - Arabic, English, Arabizi

### Safety & Security
- ✅ **No Travel Advice** - Only SmartLine bookings
- ✅ **Out-of-Context Filtering** - Blocks irrelevant questions
- ✅ **Prompt Injection Protection** - Blocks AI manipulation
- ✅ **Human Oversight** - All serious decisions by humans

---

## 🔧 How It Works

### Booking Flow (Fixed)

```
User: "اريد حجز رحلة"
Bot: "📍 من فين؟"
     [📍 موقعي] [🏠 البيت] [🏢 الشغل] [✏️ مكان تاني]

User: [📍 موقعي]
Bot: "📍 عايز تروح فين؟"
     [🏠 البيت] [🏢 الشغل] [🛒 المول] [✈️ المطار]

User: "المهندسين"
Bot: "🚗 اختر نوع السيارة:"
     [🚗 اقتصادي] [🚙 كومفورت] [🚘 بريميوم]

User: [🚗 اقتصادي]
Bot: "📋 تأكيد الحجز:
     📍 من: موقعك الحالي
     📍 إلى: المهندسين
     🚗 اقتصادي
     💰 ~25 ج.م"
     [✅ تأكيد] [🔄 تغيير] [❌ إلغاء]
```

### Smart Location Detection (Fixed)

```
User: "من العجمي ل الجيزة"
Bot: "✅ تم!
     📍 من: العجمي
     🏁 إلى: الجيزة
     
     اختار نوع السيارة:"
     [🚗 اقتصادي] [🚙 كومفورت] [🚘 بريميوم]
```

**Key:** When user provides both locations, bot **skips** pickup/destination questions and goes **directly** to vehicle selection. **NO LLM CALL** = No travel advice!

---

## 📁 Project Structure

```
ai-chat-bot-v3/
├── chat.js                      # Main server (V3.4.1)
├── classifier.js                # Intent classification
├── actions.js                   # Flutter actions
│
├── utils/
│   ├── quickReplies.js         # 🆕 Quick reply buttons
│   ├── smartResponses.js       # 🆕 Pre-built responses
│   ├── uiComponents.js         # 🆕 Smart UI builders
│   ├── evidenceHandler.js      # 🆕 File upload handler
│   ├── backofficeNotifier.js   # 🆕 Back-office alerts
│   ├── issueReporting.js       # Issue reporting (updated)
│   ├── antiFraud.js            # Anti-fraud warnings (updated)
│   ├── prompts.js              # System prompts
│   ├── language.js             # Language detection
│   ├── moderation.js           # Content moderation
│   └── ...
│
├── services/
│   ├── strikeSystem.js         # Strike recommendations (no auto-apply)
│   └── evidenceValidation.js   # Evidence analysis (advisory)
│
├── test_critical_bugs.js       # 🆕 Critical bug tests
├── test_chatbot.js             # Full test suite
└── README.md                   # This file
```

---

## 🧪 Testing

### Test Critical Bugs
```bash
node test_critical_bugs.js
```

Expected output:
```
✅ Test 1: "من العجمي ل الجيزة"
   → Pickup: "العجمي", Destination: "الجيزة"
✅ Test 2: "من مدينة نصر إلى المهندسين"
   → Pickup: "مدينة نصر", Destination: "المهندسين"
...
🎉 All critical bug tests PASSED!
```

### Test Full Chatbot
```bash
# Start server first
npm start

# In another terminal
node test_chatbot.js
```

---

## 🔒 Security Features

### 1. No Travel Advice (CRITICAL)
The bot will **NEVER** say:
- ❌ "يمكنك ركوب المترو..."
- ❌ "يمكنك ركوب الأتوبيس..."
- ❌ "يمكنك النزول في محطة..."
- ❌ "You can take the metro..."
- ❌ "You can take the bus..."

### 2. Dual Location Bypass
When user says "من X ل Y":
1. Pattern detected **BEFORE** LLM call
2. Locations extracted and saved
3. Goes directly to vehicle selection
4. **LLM is NOT called** = No chance for travel advice

### 3. Human Oversight
| Action | Who Decides |
|--------|-------------|
| Issue Reports | Human reviews |
| Strikes | Human applies |
| Bans | Human approves |
| Evidence | Human validates |

---

## ⚙️ Configuration

### Environment Variables
```env
# Server
PORT=3000
NODE_ENV=production

# Database
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=merged2

# LLM
GROQ_API_KEY=your_groq_api_key

# Back-office (optional)
BACKOFFICE_WEBHOOK_URL=https://your-webhook.com/notify
```

---

## 📊 Quick Replies Reference

| Scenario | Arabic Options | English Options |
|----------|----------------|-----------------|
| Main Menu | 🚗 احجز رحلة, 📍 تتبع رحلتي, 📋 رحلاتي, 🎧 مساعدة | 🚗 Book Ride, 📍 Track, 📋 Trips, 🎧 Help |
| Pickup | 📍 موقعي, 🏠 البيت, 🏢 الشغل, ✏️ مكان تاني | 📍 Current, 🏠 Home, 🏢 Work, ✏️ Other |
| Vehicle | 🚗 اقتصادي, 🚙 كومفورت, 🚘 بريميوم | 🚗 Economy, 🚙 Comfort, 🚘 Premium |
| Confirm | ✅ تأكيد, 🔄 تغيير, ❌ إلغاء | ✅ Confirm, 🔄 Change, ❌ Cancel |
| Issue | 🚗 السيارة, 👨‍✈️ الكابتن, 💰 السعر, ⚙️ تقني, 🚨 طوارئ | 🚗 Vehicle, 👨‍✈️ Captain, 💰 Pricing, ⚙️ Technical, 🚨 Emergency |

---

## 🐛 Troubleshooting

### Bot Gives Travel Advice
**Should not happen in V3.4.1.** If it does:
1. Check `detectDualLocation()` is being called
2. Check system prompt has `<ABSOLUTELY_FORBIDDEN>` section
3. Check LLM is not being called when locations are detected

### Bot Asks Too Many Questions
**Should not happen in V3.4.1.** If it does:
1. Check `handleStartState` asks only for pickup
2. Check quick replies are being returned
3. Check state transitions are correct

### Location Not Detected
Test with:
```bash
node test_critical_bugs.js
```
If tests fail, check the regex patterns in `detectDualLocation()`.

---

## 📈 Version History

| Version | Date | Changes |
|---------|------|---------|
| V3.4.1 | Jan 2026 | Critical bug fixes, quick replies, human oversight |
| V3.4 | Jan 2026 | Issue reporting, strike system, anti-fraud |
| V3.3 | Dec 2025 | New intents, scheduled rides, promo codes |
| V3.2 | Nov 2025 | Language enforcement, state management |

---

## 📞 Support

For issues or questions:
- Create an issue in the repository
- Contact the development team

---

## 📄 License

Proprietary - SmartLine Technologies

---

**Made with ❤️ for SmartLine** 🚗
