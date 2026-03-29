<?php

if (!function_exists('availableLanguages')) {
    function availableLanguages() {
        return [
            'en' => [
                'label' => 'English',
                'native_label' => 'English',
                'html_lang' => 'en',
            ],
            'om' => [
                'label' => 'Oromo',
                'native_label' => 'Afaan Oromoo',
                'html_lang' => 'om',
            ],
            'am' => [
                'label' => 'Amharic',
                'native_label' => 'አማርኛ',
                'html_lang' => 'am',
            ],
        ];
    }
}

if (!function_exists('translationCatalog')) {
    function translationCatalog() {
        return [
            'Student Record System' => [
                'om' => 'Sirna Galmee Barattootaa',
                'am' => 'የተማሪ መዝገብ ስርዓት',
            ],
            'Dashboard' => [
                'om' => 'Daashboordii',
                'am' => 'ዳሽቦርድ',
            ],
            'Students' => [
                'om' => 'Barattoota',
                'am' => 'ተማሪዎች',
            ],
            'Subjects' => [
                'om' => 'Barnoota',
                'am' => 'ትምህርቶች',
            ],
            'Teachers' => [
                'om' => 'Barsiisota',
                'am' => 'መምህራን',
            ],
            'Marks' => [
                'om' => 'Qabxii',
                'am' => 'ውጤቶች',
            ],
            'Summary' => [
                'om' => 'Cuunfaa',
                'am' => 'ማጠቃለያ',
            ],
            'Reports' => [
                'om' => 'Ripoortii',
                'am' => 'ሪፖርቶች',
            ],
            'Profile' => [
                'om' => 'Piroofaayilii',
                'am' => 'መገለጫ',
            ],
            'Logout' => [
                'om' => 'Baai',
                'am' => 'ውጣ',
            ],
            'Language' => [
                'om' => 'Afaan',
                'am' => 'ቋንቋ',
            ],
            'English' => [
                'om' => 'Ingiliffa',
                'am' => 'እንግሊዝኛ',
            ],
            'Oromo' => [
                'om' => 'Afaan Oromoo',
                'am' => 'ኦሮምኛ',
            ],
            'Amharic' => [
                'om' => 'Amaaraa',
                'am' => 'አማርኛ',
            ],
            'About' => [
                'om' => 'Waaee',
                'am' => 'ስለ እኛ',
            ],
            'About This System' => [
                'om' => 'Waaee Sirnichaa',
                'am' => 'ስለዚህ ስርዓት',
            ],
            'Student Record System centralizes student records, teacher assignments, marks, summaries, and reports in one school platform.' => [
                'om' => 'Sirni Galmee Barattootaa kun galmee barattootaa, ramaddii barsiisotaa, qabxii, cuunfaa fi ripoortii hunda platformii mana barumsaa tokko keessatti walitti qaba.',
                'am' => 'የተማሪ መዝገብ ስርዓቱ የተማሪ መረጃዎችን፣ የመምህራን ምደባዎችን፣ ውጤቶችን፣ ማጠቃለያዎችን እና ሪፖርቶችን በአንድ የትምህርት ቤት መድረክ ያከማቻል።',
            ],
            'Core Modules' => [
                'om' => 'Kutaa Ijoowwan',
                'am' => 'ዋና ክፍሎች',
            ],
            'Student profiles and academic records' => [
                'om' => 'Piroofaayilii barattootaa fi galmee barnootaa',
                'am' => 'የተማሪ መገለጫዎች እና የትምህርት መዝገቦች',
            ],
            'Teacher assignments and homeroom management' => [
                'om' => 'Ramaddii barsiisotaa fi bulchiinsa barsiisaa kutaa',
                'am' => 'የመምህራን ምደባ እና የክፍል መምህር አስተዳደር',
            ],
            'Marks entry, summaries, and printable reports' => [
                'om' => 'Galmee qabxii, cuunfaa fi ripoortii maxxanfamuu danda’an',
                'am' => 'የውጤት ማስገቢያ፣ ማጠቃለያ እና ሊታተሙ የሚችሉ ሪፖርቶች',
            ],
            'Distributed branch oversight for campus operations' => [
                'om' => 'Hojiiwwan kaampaasii irratti to’annoo damee raabsame',
                'am' => 'ለካምፓስ ስራዎች የተከፋፈለ የቅርንጫፍ ቁጥጥር',
            ],
            'Who Uses It' => [
                'om' => 'Eenyutu Fayyadama',
                'am' => 'ማን ይጠቀማል',
            ],
            'Super Admin manages the full platform, Branch Admin oversees campus operations, teachers manage marks, and students view results.' => [
                'om' => 'Bulchaan Olaanaan platformii guutuu bulcha; Bulchaan Damee hojii kaampaasii to’ata; barsiisonni qabxii bulchu; barattoonni immoo bu’aa isaanii ilaalu.',
                'am' => 'ዋና አስተዳዳሪው መድረኩን ሙሉ በሙሉ ያስተዳድራል፣ የቅርንጫፍ አስተዳዳሪው የካምፓስ ስራዎችን ይቆጣጠራል፣ መምህራን ውጤቶችን ያስተዳድራሉ፣ ተማሪዎችም ውጤታቸውን ያያሉ።',
            ],
            'Close' => [
                'om' => 'Cufi',
                'am' => 'ዝጋ',
            ],
            'Quick Links' => [
                'om' => 'Hidhaa Ariifachiisaa',
                'am' => 'ፈጣን አገናኞች',
            ],
            'System' => [
                'om' => 'Sirna',
                'am' => 'ስርዓት',
            ],
            'Version' => [
                'om' => 'Baayina',
                'am' => 'ስሪት',
            ],
            'Built with PHP and MySQL' => [
                'om' => 'PHP fi MySQL waliin ijaarame',
                'am' => 'በPHP እና MySQL የተገነባ',
            ],
            'All rights reserved.' => [
                'om' => 'Mirgi hundi kan eegame dha.',
                'am' => 'መብቱ ሁሉ የተጠበቀ ነው።',
            ],
            'Total Students' => [
                'om' => 'Walumaagalatti Barattoota',
                'am' => 'ጠቅላላ ተማሪዎች',
            ],
            'Total Teachers' => [
                'om' => 'Walumaagalatti Barsiisota',
                'am' => 'ጠቅላላ መምህራን',
            ],
            'Total Subjects' => [
                'om' => 'Walumaagalatti Barnoota',
                'am' => 'ጠቅላላ ትምህርቶች',
            ],
            'All registered learners currently stored in the system.' => [
                'om' => 'Barattoonni galmaa’an hundi amma sirnicha keessatti kuufamanii jiru.',
                'am' => 'በስርዓቱ ውስጥ አሁን የተመዘገቡ ተማሪዎች ሁሉ።',
            ],
            'Faculty members handling subjects, classes, and compiled results.' => [
                'om' => 'Barsiisonni barnoota, kutaa fi bu’aa walitti qabame hojii irra oolchani.',
                'am' => 'ትምህርቶችን፣ ክፍሎችን እና የተጠናቀሩ ውጤቶችን የሚያስተዳድሩ መምህራን።',
            ],
            'Courses used for marks, rankings, and final academic reports.' => [
                'om' => 'Barnootni qabxii, sadarkaa fi ripoortii xumuraa keessatti fayyadaman.',
                'am' => 'ለውጤት፣ ደረጃ እና የመጨረሻ የትምህርት ሪፖርቶች የሚጠቀሙ ትምህርቶች።',
            ],
            'Branch Oversight' => [
                'om' => 'To’annoo Damee',
                'am' => 'የቅርንጫፍ ቁጥጥር',
            ],
            'Branch Summary' => [
                'om' => 'Cuunfaa Damee',
                'am' => 'የቅርንጫፍ ማጠቃለያ',
            ],
            'All campus summary in one place' => [
                'om' => 'Cuunfaan kaampaasii hundaa bakka tokkotti',
                'am' => 'የሁሉም ካምፓስ ማጠቃለያ በአንድ ቦታ',
            ],
            'Super Admin can review each campus connection, teacher and student totals, and open branch details from here.' => [
                'om' => 'Bulchaan Olaanaan walqunnamtii kaampaasii hunda fi baayina barsiisotaa fi barattootaa ilaalee as irraa balbala damee banuu danda’a.',
                'am' => 'ዋና አስተዳዳሪው የእያንዳንዱን ካምፓስ ግንኙነት እና የመምህራን የተማሪዎች ጠቅላላ ቁጥር ከዚህ ማየት እና የቅርንጫፍ ዝርዝር መክፈት ይችላል።',
            ],
            'campuses' => [
                'om' => 'kaampaasota',
                'am' => 'ካምፓሶች',
            ],
            'Online' => [
                'om' => 'Toora irratti',
                'am' => 'በመስመር ላይ',
            ],
            'Offline' => [
                'om' => 'Toora ala',
                'am' => 'ከመስመር ውጭ',
            ],
            'Unavailable' => [
                'om' => 'Hin argamu',
                'am' => 'አይገኝም',
            ],
            'Default' => [
                'om' => 'Durtii',
                'am' => 'ነባሪ',
            ],
            'Latest activity:' => [
                'om' => 'Sochiin mootummaa dhihoo:',
                'am' => 'የቅርብ እንቅስቃሴ:',
            ],
            'Open campus details' => [
                'om' => 'Balbala damee bani',
                'am' => 'የካምፓስ ዝርዝሮችን ክፈት',
            ],
            'Open branch details' => [
                'om' => 'Balbala damee bani',
                'am' => 'የቅርንጫፍ ዝርዝሮችን ክፈት',
            ],
            'Academic Summary' => [
                'om' => 'Cuunfaa Barnootaa',
                'am' => 'የትምህርት ማጠቃለያ',
            ],
            'Open Mark Entry' => [
                'om' => 'Galmee Qabxii Bani',
                'am' => 'የውጤት ማስገቢያ ክፈት',
            ],
            'Open Reports' => [
                'om' => 'Ripoortii Bani',
                'am' => 'ሪፖርቶችን ክፈት',
            ],
            'Open Branch Oversight' => [
                'om' => 'To’annoo Damee Bani',
                'am' => 'የቅርንጫፍ ቁጥጥር ክፈት',
            ],
            'Learners currently tracked in the system.' => [
                'om' => 'Barattoonni yeroo ammaa sirnicha keessatti hordofaman.',
                'am' => 'በአሁኑ ጊዜ በስርዓቱ ውስጥ የሚከታተሉ ተማሪዎች።',
            ],
            'Faculty records linked to grades, subjects, and reports.' => [
                'om' => 'Galmeen barsiisotaa sadarkaa, barnoota fi ripoortii waliin walqabata.',
                'am' => 'ከደረጃዎች ከትምህርቶች እና ከሪፖርቶች ጋር የተገናኙ የመምህራን መዝገቦች።',
            ],
            'Courses contributing to progress and reports.' => [
                'om' => 'Barnootni guddina fi ripoortiif gumaachan.',
                'am' => 'ለእድገት እና ለሪፖርቶች የሚያበረክቱ ኮርሶች።',
            ],
            'Recorded assessments available for analysis.' => [
                'om' => 'Qorannoowwan galmaa’an xiinxalaaf qophaa’an.',
                'am' => 'ለትንተና ዝግጁ የተመዘገቡ ግምገማዎች።',
            ],
            'Focus' => [
                'om' => 'Xiyyeeffannoo',
                'am' => 'ትኩረት',
            ],
            'Average recorded marks per subject slot.' => [
                'om' => 'Barnoota tokko tokko keessatti giddugaleessa qabxii galmaa’e.',
                'am' => 'በእያንዳንዱ የትምህርት ክፍል የተመዘገበ አማካይ ውጤት።',
            ],
            'Quick Actions' => [
                'om' => 'Tarkaanfii Ariifachiisaa',
                'am' => 'ፈጣን እርምጃዎች',
            ],
            'Manage Students' => [
                'om' => 'Barattoota Bulchi',
                'am' => 'ተማሪዎችን አስተዳድር',
            ],
            'Manage Subjects' => [
                'om' => 'Barnoota Bulchi',
                'am' => 'ትምህርቶችን አስተዳድር',
            ],
            'Manage Teachers' => [
                'om' => 'Barsiisota Bulchi',
                'am' => 'መምህራንን አስተዳድር',
            ],
            'Review Summary' => [
                'om' => 'Cuunfaa Ilaali',
                'am' => 'ማጠቃለያን አርም',
            ],
            'Generate Reports' => [
                'om' => 'Ripoortii Uumi',
                'am' => 'ሪፖርቶችን ፍጠር',
            ],
            'My Class Roster' => [
                'om' => 'Galmee Kutaakoo',
                'am' => 'የክፍሌ ዝርዝር',
            ],
            'My Subjects' => [
                'om' => 'Barnoota Koo',
                'am' => 'ትምህርቶቼ',
            ],
            'Enter Marks' => [
                'om' => 'Qabxii Galchi',
                'am' => 'ውጤት አስገባ',
            ],
            'Compile Results' => [
                'om' => 'Bu’aa Walitti Qabi',
                'am' => 'ውጤቶችን አጠናቅቅ',
            ],
            'Assigned Students' => [
                'om' => 'Barattoota Ramadaman',
                'am' => 'የተመደቡ ተማሪዎች',
            ],
            'Submit Marks' => [
                'om' => 'Qabxii Ergi',
                'am' => 'ውጤት አስገባ',
            ],
            'View My Report' => [
                'om' => 'Ripoortii Koo Ilaali',
                'am' => 'ሪፖርቴን ይመልከቱ',
            ],
            'Update Profile' => [
                'om' => 'Piroofaayilii Haaromsi',
                'am' => 'መገለጫን አዘምን',
            ],
            'Reliable record management for classes, teachers, marks, and reports.' => [
                'om' => 'Kutaa, barsiisota, qabxii fi ripoortii irratti bulchiinsa galmee amanamaa.',
                'am' => 'ለክፍሎች፣ ለመምህራን፣ ለውጤቶች እና ለሪፖርቶች አስተማማኝ የመዝገብ አስተዳደር።',
            ],
            'Role-based access keeps admin, teachers, homeroom teachers, and students inside their correct workflow.' => [
                'om' => 'Seensa gahee irratti hundaa’e hojii sirrii keessatti hojii mootummaa, barsiisota, barsiisota kutaa fi barattoota eega.',
                'am' => 'በሚና ላይ የተመሠረተ መዳረሻ አስተዳዳሪዎችን፣ መምህራንን፣ የክፍል መምህራንን እና ተማሪዎችን በትክክለኛ የስራ ፍሰት ውስጥ ያቆያል።',
            ],
            'Sign In' => [
                'om' => 'Seeni',
                'am' => 'ግባ',
            ],
            'Username or Email' => [
                'om' => 'Maqaa Fayyadamaa yookaan Imeelii',
                'am' => 'የተጠቃሚ ስም ወይም ኢሜይል',
            ],
            'Email' => [
                'om' => 'Imeelii',
                'am' => 'ኢሜይል',
            ],
            'Password' => [
                'om' => 'Jecha Icciitii',
                'am' => 'የይለፍ ቃል',
            ],
            'Role' => [
                'om' => 'Gahee',
                'am' => 'ሚና',
            ],
            'Super Admin' => [
                'om' => 'Bulchaa Olaanaa',
                'am' => 'ዋና አስተዳዳሪ',
            ],
            'Branch Admin' => [
                'om' => 'Bulchaa Damee',
                'am' => 'የቅርንጫፍ አስተዳዳሪ',
            ],
            'Teacher' => [
                'om' => 'Barsiisaa',
                'am' => 'መምህር',
            ],
            'Student' => [
                'om' => 'Barataa',
                'am' => 'ተማሪ',
            ],
            'Enter your email or username and password to continue.' => [
                'om' => 'Itti fufuuf imeelii yookaan maqaa fayyadamaa fi jecha icciitii galchi.',
                'am' => 'ለመቀጠል ኢሜይልዎን ወይም የተጠቃሚ ስምዎን እና የይለፍ ቃልዎን ያስገቡ።',
            ],
            'Enter your email and password to continue.' => [
                'om' => 'Itti fufuuf imeelii fi jecha icciitii kee galchi.',
                'am' => 'ለመቀጠል ኢሜይልዎን እና የይለፍ ቃልዎን ያስገቡ።',
            ],
            'Please enter a valid email address.' => [
                'om' => 'Maaloo teessoo imeelii sirrii galchi.',
                'am' => 'እባክዎ ትክክለኛ የኢሜይል አድራሻ ያስገቡ።',
            ],
            'Login failed. Please verify your credentials and selected role.' => [
                'om' => 'Seensichi hin milkoofne. Maaloo ragaa kee fi gahee filatame mirkaneessi.',
                'am' => 'መግቢያው አልተሳካም። እባክዎ መረጃዎን እና የተመረጠውን ሚና ያረጋግጡ።',
            ],
            'Details' => [
                'om' => 'Bal’ina',
                'am' => 'ዝርዝር',
            ],
            'Branch Details' => [
                'om' => 'Bal’ina Damee',
                'am' => 'የቅርንጫፍ ዝርዝር',
            ],
            'No activity yet' => [
                'om' => 'Amma sochiin hin jiru',
                'am' => 'እስካሁን እንቅስቃሴ የለም',
            ],
            'N/A' => [
                'om' => 'Hin jiru',
                'am' => 'የለም',
            ],
            'Branch-level records visible to the main admin.' => [
                'om' => 'Galmeen sadarkaa damee bulchaa olaanaatiif mul’ata.',
                'am' => 'የቅርንጫፍ ደረጃ መዝገቦች ለዋና አስተዳዳሪ ይታያሉ።',
            ],
            'Back to Dashboard' => [
                'om' => 'Gara Daashboordiitti Deebi’i',
                'am' => 'ወደ ዳሽቦርድ ተመለስ',
            ],
            'Distributed branch oversight is not enabled in the current system.' => [
                'om' => 'To’annoon damee raabsame sirna amma jiru keessatti hin dandeessifamne.',
                'am' => 'በአሁኑ ስርዓት ውስጥ የተከፋፈለ የቅርንጫፍ ቁጥጥር አልነቃም።',
            ],
            'The selected branch campus could not be found.' => [
                'om' => 'Kaampaasii damee filatame argamuu hin dandeenye.',
                'am' => 'የተመረጠው የቅርንጫፍ ካምፓስ ሊገኝ አልቻለም።',
            ],
            'Campus' => [
                'om' => 'Kaampaasii',
                'am' => 'ካምፓስ',
            ],
            'Database' => [
                'om' => 'Kuusdeetaa',
                'am' => 'ዳታቤዝ',
            ],
            'Connection' => [
                'om' => 'Walqunnamtii',
                'am' => 'ግንኙነት',
            ],
            'Latest Activity' => [
                'om' => 'Sochii Dhihoo',
                'am' => 'የቅርብ እንቅስቃሴ',
            ],
            'This branch database is currently unavailable, so only branch metadata can be shown.' => [
                'om' => 'Kuusdeetaan damee kun amma hin argamu; kanaaf odeeffannoon waliigalaa damee qofa mul’achuu danda’a.',
                'am' => 'ይህ የቅርንጫፍ ዳታቤዝ አሁን አይገኝም፣ ስለዚህ የቅርንጫፍ መረጃ ብቻ ሊታይ ይችላል።',
            ],
            'No teachers found in this branch.' => [
                'om' => 'Damee kana keessatti barsiisotni hin argamne.',
                'am' => 'በዚህ ቅርንጫፍ ውስጥ መምህራን አልተገኙም።',
            ],
            'ID' => [
                'om' => 'ID',
                'am' => 'መለያ',
            ],
            'Name' => [
                'om' => 'Maqaa',
                'am' => 'ስም',
            ],
            'Assigned Grade' => [
                'om' => 'Kutaa Ramadame',
                'am' => 'የተመደበ ክፍል',
            ],
            'Homeroom' => [
                'om' => 'Barsiisaa Kutaa',
                'am' => 'የክፍል መምህር',
            ],
            'Created' => [
                'om' => 'Kan Uumame',
                'am' => 'የተፈጠረ',
            ],
            'Yes' => [
                'om' => 'Eeyyee',
                'am' => 'አዎ',
            ],
            'No' => [
                'om' => 'Lakki',
                'am' => 'አይ',
            ],
            'No subjects found in this branch.' => [
                'om' => 'Damee kana keessatti barnoonni hin argamne.',
                'am' => 'በዚህ ቅርንጫፍ ውስጥ ትምህርቶች አልተገኙም።',
            ],
            'Subject' => [
                'om' => 'Barnoota',
                'am' => 'ትምህርት',
            ],
            'Total Mark' => [
                'om' => 'Qabxii Waliigalaa',
                'am' => 'ጠቅላላ ውጤት',
            ],
            'Teachers Assigned' => [
                'om' => 'Barsiisota Ramadaman',
                'am' => 'የተመደቡ መምህራን',
            ],
            'No students found in this branch.' => [
                'om' => 'Damee kana keessatti barattoonni hin argamne.',
                'am' => 'በዚህ ቅርንጫፍ ውስጥ ተማሪዎች አልተገኙም።',
            ],
            'Gender' => [
                'om' => 'Saala',
                'am' => 'ጾታ',
            ],
            'Grade' => [
                'om' => 'Kutaa',
                'am' => 'ክፍል',
            ],
            'Academic Year' => [
                'om' => 'Waggaa Barnootaa',
                'am' => 'የትምህርት ዓመት',
            ],
            'Semester' => [
                'om' => 'Semisteera',
                'am' => 'ሴሚስተር',
            ],
            'No marks found in this branch.' => [
                'om' => 'Damee kana keessatti qabxiin hin argamne.',
                'am' => 'በዚህ ቅርንጫፍ ውስጥ ውጤቶች አልተገኙም።',
            ],
            'Score' => [
                'om' => 'Qabxii',
                'am' => 'ነጥብ',
            ],
            'Type' => [
                'om' => 'Gosa',
                'am' => 'አይነት',
            ],
            'Assessment Date' => [
                'om' => 'Guyyaa Madaallii',
                'am' => 'የግምገማ ቀን',
            ],
            'Saved' => [
                'om' => 'Kan Kuufame',
                'am' => 'የተቀመጠ',
            ],
        ];
    }
}

if (!function_exists('normalizeLanguage')) {
    function normalizeLanguage($language) {
        $language = strtolower(trim((string)$language));
        return array_key_exists($language, availableLanguages()) ? $language : 'en';
    }
}

if (!function_exists('setCurrentLanguage')) {
    function setCurrentLanguage($language) {
        $language = normalizeLanguage($language);
        $_SESSION['app_language'] = $language;
        return $language;
    }
}

if (!function_exists('currentLanguage')) {
    function currentLanguage() {
        if (!empty($_GET['lang'])) {
            return setCurrentLanguage($_GET['lang']);
        }

        if (!empty($_POST['app_language'])) {
            return setCurrentLanguage($_POST['app_language']);
        }

        if (!empty($_SESSION['app_language'])) {
            return normalizeLanguage($_SESSION['app_language']);
        }

        return setCurrentLanguage('en');
    }
}

if (!function_exists('currentLanguageTag')) {
    function currentLanguageTag() {
        $language = currentLanguage();
        $languages = availableLanguages();
        return $languages[$language]['html_lang'] ?? 'en';
    }
}

if (!function_exists('t')) {
    function t($text) {
        $language = currentLanguage();
        if ($language === 'en') {
            return $text;
        }

        $catalog = translationCatalog();
        return $catalog[$text][$language] ?? $text;
    }
}

if (!function_exists('languageSwitcherUrl')) {
    function languageSwitcherUrl($basePath = '') {
        $basePath = rtrim((string)$basePath, '/');
        if ($basePath !== '') {
            $basePath .= '/';
        }

        $requestPath = $_SERVER['PHP_SELF'] ?? ($basePath . 'index.php');
        $scriptName = basename((string)$requestPath);
        return $basePath . $scriptName;
    }
}

if (!function_exists('renderLanguageSwitcher')) {
    function renderLanguageSwitcher($basePath = '') {
        $languages = availableLanguages();
        $current = currentLanguage();
        $action = languageSwitcherUrl($basePath);

        ob_start();
        ?>
        <form method="get" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="d-flex align-items-center gap-2 language-switcher">
            <label class="text-white small mb-0" for="app_language_switcher"><?php echo htmlspecialchars(t('Language'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select form-select-sm" id="app_language_switcher" name="lang" onchange="this.form.submit()">
                <?php foreach ($languages as $code => $language): ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $current === $code ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($language['native_label'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
        return ob_get_clean();
    }
}

currentLanguage();
?>