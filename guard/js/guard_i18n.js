/**
 * Mombasa Mall Basement Parking - Guard Interface Localization (EN / SW)
 * Full bilingual support for security guards.
 */

const I18N = {
    en: {
        mall_title: "MOMBASA MALL BASEMENT",
        guard_booth: "Guard Gate Station",
        login_title: "Security Guard Login",
        login_prompt: "Enter your 4-digit security PIN",
        login_btn: "ENTER PIN",
        clear_btn: "CLEAR",
        logout: "Logout",
        language: "Lugha: Kiswahili",
        switch_lang: "sw",
        
        // Status dots
        status_internet_ok: "Mall LAN: Online",
        status_internet_off: "Mall LAN: Offline",
        status_printer_ok: "Printer: Ready",
        status_printer_warn: "Printer: Check Spooler",
        status_cam_in_ok: "Entry Cam: Active",
        status_cam_in_off: "Entry Cam: Offline",
        status_cam_out_ok: "Exit Cam: Active",
        status_cam_out_off: "Exit Cam: Offline",

        // Occupancy
        total_slots: "TOTAL SLOTS",
        occupied: "OCCUPIED",
        available: "AVAILABLE SPACES",
        parking_full: "PARKING FULL - NO SLOTS",

        // Tabs
        tab_entering: "CARS ENTERING",
        tab_leaving: "CARS LEAVING",
        tab_inside: "CARS INSIDE NOW",
        add_manual: "+ ADD CAR MANUALLY",
        help: "Help Guide",

        // Entering
        cam_sees_in: "Entrance Camera sees:",
        pending_queue: "Waiting for Ticket",
        verified_badge: "VERIFIED BY CAMERA",
        self_registered: "Driver Self Sign-in",
        manual_entry: "Manual Gate Entry",
        btn_accept_print: "ACCEPT & PRINT",
        btn_reject: "REJECT",
        no_pending: "No cars waiting right now. Ready for next vehicle.",

        // Leaving
        cam_sees_out: "Exit Camera sees:",
        search_prompt: "Search plate number, phone, or ticket ID...",
        btn_clear_exit: "CLEAR EXIT",
        parked_time: "Time Parked:",
        entry_time: "Entry Time:",
        destination: "Destination:",
        driver: "Driver:",
        no_car_found: "No active record found for this vehicle.",
        override_prompt: "Ask supervisor for override PIN if car is departing.",

        // Wizard
        wiz_title: "Manual Car Registration",
        wiz_step1_title: "1. Vehicle Number Plate",
        wiz_step1_sub: "Enter Kenyan plate (e.g. KDA 123A)",
        wiz_use_cam: "Use Camera Plate:",
        wiz_step2_title: "2. Driver's Full Name",
        wiz_step2_sub: "Ask driver for their name (or Visitor)",
        wiz_step3_title: "3. Driver's Phone Number",
        wiz_step3_sub: "For free WhatsApp parking ticket",
        wiz_step4_title: "4. Where is the driver going?",
        wiz_step4_sub: "Select destination store or office",
        wiz_step5_title: "5. Confirm & Print Ticket",
        btn_next: "NEXT STEP",
        btn_back: "BACK",
        btn_confirm_print: "CONFIRM & PRINT TICKET",
        btn_cancel: "CANCEL",

        // Sound
        tap_sound_enable: "Tap here to enable alert chime",
        sound_on: "Sound: ON",
        sound_muted: "Sound: MUTED",

        // Alerts & Overstay
        overstay_warn: "OVERSTAY ALERT (> 8 Hours)",
        blacklisted_alert: "SECURITY ALERT: BLACKLISTED VEHICLE",
        vip_alert: "VIP GUEST: RESERVED AREA",
        staff_alert: "STAFF / TENANT VEHICLE",

        // Messages
        ticket_success: "Ticket Printed! Hand ticket to driver.",
        ticket_failed: "Printer did not respond. Check paper & press REPRINT.",
        btn_reprint: "REPRINT TICKET",
    },
    sw: {
        mall_title: "MOMBASA MALL BASEMENT",
        guard_booth: "Kituo cha Walinzi",
        login_title: "Kuingia kwa Mlinzi",
        login_prompt: "Weka nambari yako ya siri (PIN ya tarakimu 4)",
        login_btn: "WEKA PIN",
        clear_btn: "FUTA",
        logout: "Toka",
        language: "Language: English",
        switch_lang: "en",
        
        // Status dots
        status_internet_ok: "Mtandao: Sawa",
        status_internet_off: "Mtandao: Haupo",
        status_printer_ok: "Mashine ya Tikiti: Tayari",
        status_printer_warn: "Mashine: Kagua Karatasi",
        status_cam_in_ok: "Kamera ya Kuingia: Inafanya kazi",
        status_cam_in_off: "Kamera ya Kuingia: Haionekani",
        status_cam_out_ok: "Kamera ya Kutoka: Inafanya kazi",
        status_cam_out_off: "Kamera ya Kutoka: Haionekani",

        // Occupancy
        total_slots: "JUMLA YA NAFASI",
        occupied: "ZILIZOJAA",
        available: "NAFASI ZILIZO WAZI",
        parking_full: "PARKING IMEJAA - HAKUNA NAFASI",

        // Tabs
        tab_entering: "MAGARI YANAYOINGIA",
        tab_leaving: "MAGARI YANAYOTOKA",
        tab_inside: "MAGARI YALIYO NDANI",
        add_manual: "+ ANDIKISHA GARI HAPA",
        help: "Mwongozo wa Msaada",

        // Entering
        cam_sees_in: "Kamera ya kuingia inaona:",
        pending_queue: "Wanaosubiri Tikiti",
        verified_badge: "IMETHIBITISHWA NA KAMERA",
        self_registered: "Dereva Ajiandikisha kwa Simu",
        manual_entry: "Imeandikishwa na Mlinzi",
        btn_accept_print: "KUBALI NA UTOE TIKITI",
        btn_reject: "KATAA",
        no_pending: "Hakuna gari linalosubiri sasa. Lango liko wazi.",

        // Leaving
        cam_sees_out: "Kamera ya kutoka inaona:",
        search_prompt: "Tafuta nambari ya gari, simu, au tikiti...",
        btn_clear_exit: "RUHUSU LITOKE",
        parked_time: "Muda Uliokaa:",
        entry_time: "Muda wa Kuingia:",
        destination: "Duka / Eneo:",
        driver: "Dereva:",
        no_car_found: "Hakuna rekodi ya gari hili kuingia.",
        override_prompt: "Wasiliana na Mkuu wa Walinzi (Supervisor) kwa nambari ya siri.",

        // Wizard
        wiz_title: "Kuandikisha Gari kwa Mkono",
        wiz_step1_title: "1. Nambari ya Bamba la Gari",
        wiz_step1_sub: "Weka nambari ya gari (mfano KDA 123A)",
        wiz_use_cam: "Tumia Nambari ya Kamera:",
        wiz_step2_title: "2. Jina Kamili la Dereva",
        wiz_step2_sub: "Muulize dereva jina lake (au Mgeni)",
        wiz_step3_title: "3. Nambari ya Simu ya Dereva",
        wiz_step3_sub: "Kwa ajili ya tikiti ya bure ya WhatsApp",
        wiz_step4_title: "4. Dereva Anaelekea Eneo Gani?",
        wiz_step4_sub: "Chagua duka au ofisi anayotembelea",
        wiz_step5_title: "5. Hakiki na Utoe Tikiti",
        btn_next: "ENDELEA MBELE",
        btn_back: "RUDI NYUMA",
        btn_confirm_print: "THIBITISHA NA UTOE TIKITI",
        btn_cancel: "HAIRISHA",

        // Sound
        tap_sound_enable: "Bonyeza hapa kuwasha sauti ya kengele",
        sound_on: "Sauti: IMEWASHWA",
        sound_muted: "Sauti: IMEZIMWA",

        // Alerts & Overstay
        overstay_warn: "ONYO LA KUKAA ZAIDI (> Masaa 8)",
        blacklisted_alert: "ONYO LA USALAMA: GARI LILILOZUILIWA",
        vip_alert: "MGENI MAALUM (VIP): ENEO MAALUM",
        staff_alert: "GARI LA MFANYAKAZI / MPANGAJI",

        // Messages
        ticket_success: "Tikiti Imetoka! Mpatie dereva aingie.",
        ticket_failed: "Printa haijachapisha. Kagua karatasi kisha bonyeza CHAPISHA TENA.",
        btn_reprint: "CHAPISHA TENA",
    }
};

let currentLang = localStorage.getItem('mm_guard_lang') || 'en';

function t(key) {
    if (I18N[currentLang] && I18N[currentLang][key]) {
        return I18N[currentLang][key];
    }
    if (I18N.en && I18N.en[key]) {
        return I18N.en[key];
    }
    return key;
}

function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('mm_guard_lang', lang);
    applyTranslations();
}

function toggleLanguage() {
    const next = currentLang === 'en' ? 'sw' : 'en';
    setLanguage(next);
}

function applyTranslations() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const k = el.getAttribute('data-i18n');
        if (k) el.textContent = t(k);
    });

    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const k = el.getAttribute('data-i18n-placeholder');
        if (k) el.placeholder = t(k);
    });

    const langBtn = document.getElementById('btnLangToggle');
    if (langBtn) {
        langBtn.textContent = t('language');
    }
}
