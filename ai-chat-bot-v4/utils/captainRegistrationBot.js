// ============================================
// 🚖 CAPTAIN REGISTRATION STATUS CHATBOT
// ============================================
// Converted from chatbot_capt.py
// Handles ONLY captain registration status inquiries

/**
 * Get captain registration status response
 * @param {string} captainName - Captain's name
 * @param {string} language - Language preference (ar/en/arabizi)
 * @param {string} registrationStatus - Status from database
 * @returns {Object} Response object
 */
function getCaptainRegistrationResponse(captainName, language, registrationStatus) {
    const cleanName = captainName || 'Captain';
    const lang = ['ar', 'en', 'arabizi'].includes(language) ? language : 'ar';
    const status = registrationStatus?.toLowerCase() || 'unknown';

    const responses = {
        // Under Review
        under_review: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لتواصلك معنا!

طلب التسجيل الخاص بك قيد المراجعة حالياً من قبل فريقنا المختص. نحن نعمل على مراجعة جميع المستندات بعناية لضمان أفضل تجربة لك.

سنقوم بإشعارك فور الانتهاء من المراجعة.

نقدر صبرك وتفهمك 🙏`,

            en: `Hello Captain ${cleanName} 👋

Thank you for reaching out!

Your registration request is currently under review by our team. We are carefully reviewing all your documents to ensure the best experience for you.

You will be notified as soon as the review is complete.

We appreciate your patience 🙏`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala el tawasol!

Talab el tasjeel beta3ak 7alyan under review men el team beta3na. E7na bنراجع kol el documents beta3tak b3enaya.

Ha neb3atlak notification awel ma nkhalas.

Neshkor sabrak 🙏`
        },

        // Documents Missing
        documents_missing: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لتواصلك معنا.

لاحظنا أن بعض المستندات المطلوبة ناقصة أو تحتاج إلى تحديث.

📄 الخطوات المطلوبة:
• افتح التطبيق وادخل على حسابك
• اذهب إلى قسم "المستندات"
• ارفع المستندات الناقصة بصورة واضحة
• تأكد أن جميع الوثائق سارية المفعول

بمجرد استلام المستندات الكاملة، سنراجع طلبك فوراً ✅

نحن هنا لمساعدتك!`,

            en: `Hello Captain ${cleanName} 👋

Thank you for contacting us.

We noticed that some required documents are missing or need to be updated.

📄 Required Steps:
• Open the app and log into your account
• Go to the "Documents" section
• Upload the missing documents in clear quality
• Make sure all documents are valid and not expired

Once we receive the complete documents, we'll review your request right away ✅

We're here to help!`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala el tawasol.

La7azna en fi documents na2sa aw me7taga update.

📄 El Khatawat el Matloba:
• Efta7 el app w login 3ala account-ak
• Ro7 3ala section el "Documents"
• Upload el documents el na2sa b sora wade7a
• Eta2kad en kol el documents sari7a

Awel ma nestalem el documents kamla, ha nراجع talab-ak 3ala tool ✅

E7na hena 3ashan nesa3dak!`
        },

        // Approved
        approved: {
            ar: `مبروك كابتن ${cleanName}! 🎉

يسعدنا إخبارك بأن طلب التسجيل الخاص بك قد تمت الموافقة عليه!

✅ يمكنك الآن:
• تسجيل الدخول إلى حسابك
• تفعيل وضع "متصل"
• البدء في قبول الرحلات
• تحقيق الأرباح!

مرحباً بك في العائلة! نتمنى لك رحلة موفقة 🚗

بالتوفيق!`,

            en: `Congratulations Captain ${cleanName}! 🎉

We're happy to inform you that your registration has been approved!

✅ You can now:
• Log into your account
• Turn on "Online" mode
• Start accepting rides
• Start earning!

Welcome to the family! We wish you a great journey 🚗

Good luck!`,

            arabizi: `Mabrook Captain ${cleanName}! 🎉

Mabsooteen n2ollak en talab el tasjeel beta3ak etm el mowaf2a 3aleh!

✅ Delwa2ty te2dar:
• Login 3ala account-ak
• Sha8al "Online" mode
• Tebda2 te2bal re7lat
• Tebda2 teksب floos!

Ahlan bik fi el 3aila! Netmannalek re7la mowafa2a 🚗

Bel tawfi2!`
        },

        // Rejected
        rejected: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لاهتمامك بالانضمام إلينا.

نأسف لإبلاغك بأن طلب التسجيل الخاص بك لم يتم قبوله في الوقت الحالي.

🔄 خياراتك:
• التواصل مع فريق الدعم لمعرفة التفاصيل
• إعادة التقديم بعد معالجة أسباب الرفض
• تقديم استئناف إذا كنت تعتقد أن هناك خطأ

للاستفسار، نحن هنا لمساعدتك.

نتمنى لك كل التوفيق 🙏`,

            en: `Hello Captain ${cleanName} 👋

Thank you for your interest in joining us.

We regret to inform you that your registration request has not been accepted at this time.

🔄 Your options:
• Contact our support team for more details
• Reapply after addressing the rejection reasons
• Submit an appeal if you believe there was an error

For inquiries, we're here to help.

We wish you all the best 🙏`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala ehtimamak bel join ma3ana.

Mota2asfeen n2ollak en talab el tasjeel beta3ak ma etmش 2aboloh delwa2ty.

🔄 El e5tiyarat beta3tak:
• Etواصل ma3 el support team 3ashan ta3raf el tafaseel
• 2addem tany ba3d ma t3aleg asbab el rafd
• 2addem appeal law fakker en fi 8alat

Lel este5sarat, e7na hena 3ashan nesa3dak.

Netmannalek kol el tawfi2 🙏`
        },

        // Background Check
        background_check: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لتواصلك معنا.

طلب التسجيل الخاص بك يخضع حالياً للفحص الأمني. هذه خطوة ضرورية لضمان سلامة جميع المستخدمين على المنصة.

🔒 معلومات مهمة:
• هذه العملية قد تستغرق بضعة أيام
• لا تحتاج لاتخاذ أي إجراء
• سنشعرك فور اكتمال الفحص

نشكر صبرك وتعاونك! 🙏`,

            en: `Hello Captain ${cleanName} 👋

Thank you for reaching out.

Your registration is currently undergoing a background check. This is a necessary step to ensure the safety of all users on our platform.

🔒 Important information:
• This process may take a few days
• No action is required from you
• You'll be notified once the check is complete

Thank you for your patience and cooperation! 🙏`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala el tawasol.

Talab el tasjeel beta3ak 7alyan fi marhalet el fa7s el amny. Dي step darورiya 3ashan ned-man safety kol el users 3al platform.

🔒 Ma3lomat mohemma:
• El process da momken yakhod kam yom
• Mesh me7tag te3mel ay 7aga
• Ha neb3atlak notification awel ma nkhalas

Neshkor sabrak w ta3awonak! 🙏`
        },

        // System Delay
        system_delay: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لتواصلك معنا.

نعتذر عن التأخير في معالجة طلبك. نواجه حالياً ضغطاً كبيراً على النظام بسبب كثرة الطلبات.

⏳ ما يجب أن تعرفه:
• طلبك في قائمة الانتظار ولن يُفقد
• نعمل بأقصى سرعة لمراجعة جميع الطلبات
• سنتواصل معك فور تحديث حالة طلبك

نقدر صبرك الكبير ونعتذر مجدداً عن الإزعاج 🙏`,

            en: `Hello Captain ${cleanName} 👋

Thank you for reaching out.

We apologize for the delay in processing your request. We're currently experiencing high volume due to many applications.

⏳ What you should know:
• Your request is in queue and won't be lost
• We're working as fast as possible to review all requests
• We'll contact you once your status is updated

We appreciate your patience and apologize for any inconvenience 🙏`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala el tawasol.

Beta3tezر 3an el ta2kheer fi mo3alget talab-ak. 3andena daght kebeer 3al system delwa2ty bisabab ketret el talabat.

⏳ El lazem ta3rafo:
• Talab-ak fi el queue w mesh ha yed-ya3
• E7na shaghaleen bأقصى sor3a 3ashan nراجع kol el talabat
• Ha netواصل ma3ak awel ma 7alet talab-ak tet-update

Neshkor sabrak gedan w beta3tezر tany 3an el ez3ag 🙏`
        },

        // Unknown/Default
        unknown: {
            ar: `مرحباً كابتن ${cleanName} 👋

شكراً لتواصلك معنا.

للاستفسار عن حالة التسجيل الخاصة بك، يمكنك:
• التحقق من التطبيق في قسم "الملف الشخصي"
• التواصل مع فريق الدعم مباشرة
• إرسال بريد إلكتروني إلى support@smartline-it.com

نحن هنا لمساعدتك! 🎧`,

            en: `Hello Captain ${cleanName} 👋

Thank you for contacting us.

To inquire about your registration status, you can:
• Check the app in the "Profile" section
• Contact our support team directly
• Send an email to support@smartline-it.com

We're here to help! 🎧`,

            arabizi: `Ahlan Captain ${cleanName} 👋

Shokran 3ala el tawasol.

3ashan te3raf 7alet el tasjeel beta3ak, momken:
• Check el app fi section el "Profile"
• Etwasel ma3 el support team
• Eb3at email 3ala support@smartline-it.com

E7na hena 3ashan nesa3dak! 🎧`
        }
    };

    // Get the response template
    const template = responses[status] || responses.unknown;
    const message = template[lang] || template.ar;

    return {
        message,
        action: 'captain_registration_status',
        data: {
            captain_name: cleanName,
            registration_status: status,
            language: lang
        },
        quick_replies: getQuickReplies(status, lang),
        userType: 'captain',
        language: lang
    };
}

/**
 * Get quick replies based on status and language
 */
function getQuickReplies(status, lang) {
    const replies = {
        under_review: {
            ar: ['📄 المستندات المطلوبة', '📞 التواصل مع الدعم'],
            en: ['📄 Required Documents', '📞 Contact Support'],
            arabizi: ['📄 El Documents', '📞 Contact Support']
        },
        documents_missing: {
            ar: ['📤 رفع المستندات', '❓ المستندات المطلوبة', '📞 مساعدة'],
            en: ['📤 Upload Documents', '❓ Required Documents', '📞 Help'],
            arabizi: ['📤 Upload', '❓ Documents', '📞 Help']
        },
        approved: {
            ar: ['🚗 ابدأ العمل', '📖 دليل الكابتن', '📞 الدعم الفني'],
            en: ['🚗 Start Working', '📖 Captain Guide', '📞 Tech Support'],
            arabizi: ['🚗 Start', '📖 Guide', '📞 Support']
        },
        rejected: {
            ar: ['📞 التواصل مع الدعم', '🔄 إعادة التقديم', '📋 تفاصيل الرفض'],
            en: ['📞 Contact Support', '🔄 Reapply', '📋 Rejection Details'],
            arabizi: ['📞 Support', '🔄 Reapply', '📋 Details']
        },
        background_check: {
            ar: ['⏱️ متى ينتهي الفحص؟', '📞 التواصل مع الدعم'],
            en: ['⏱️ When will it finish?', '📞 Contact Support'],
            arabizi: ['⏱️ Emta yخلص?', '📞 Support']
        },
        system_delay: {
            ar: ['⏳ حالة الطلب', '📞 التواصل مع الدعم'],
            en: ['⏳ Request Status', '📞 Contact Support'],
            arabizi: ['⏳ Status', '📞 Support']
        }
    };

    return replies[status]?.[lang] || ['📞 مساعدة', '📋 معلومات'];
}

/**
 * Get captain registration status from database
 * @param {string} userId - User ID
 * @param {Function} dbQuery - Database query function
 * @returns {Promise<Object>} Status information
 */
async function getCaptainRegistrationStatus(userId, dbQuery) {
    try {
        const rows = await dbQuery(`
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.user_role,
                d.is_verified,
                d.is_active,
                d.approval_status,
                d.rejection_reason,
                d.license_number,
                d.vehicle_registration_number,
                d.created_at
            FROM users u
            LEFT JOIN drivers d ON u.id = d.user_id
            WHERE u.id = ? AND u.user_role = 'driver'
        `, [userId]);

        if (rows.length === 0) {
            return {
                found: false,
                status: 'not_captain',
                message: 'User is not registered as a captain'
            };
        }

        const captain = rows[0];
        const captainName = `${captain.first_name || ''} ${captain.last_name || ''}`.trim();

        // Determine registration status
        let status = 'under_review'; // Default

        if (captain.approval_status === 'approved' && captain.is_verified && captain.is_active) {
            status = 'approved';
        } else if (captain.approval_status === 'rejected') {
            status = 'rejected';
        } else if (captain.approval_status === 'pending') {
            // Check if documents are missing
            if (!captain.license_number || !captain.vehicle_registration_number) {
                status = 'documents_missing';
            } else {
                status = 'background_check';
            }
        } else if (captain.approval_status === 'documents_required') {
            status = 'documents_missing';
        }

        return {
            found: true,
            status,
            captain: {
                name: captainName,
                is_verified: captain.is_verified,
                is_active: captain.is_active,
                approval_status: captain.approval_status,
                rejection_reason: captain.rejection_reason
            }
        };
    } catch (error) {
        console.error('[CaptainRegistrationBot] Database error:', error.message);
        return {
            found: false,
            status: 'system_delay',
            error: error.message
        };
    }
}

module.exports = {
    getCaptainRegistrationResponse,
    getCaptainRegistrationStatus,
    getQuickReplies
};

