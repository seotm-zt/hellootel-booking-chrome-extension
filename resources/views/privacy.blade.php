<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy — Booking Saver</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 16px;
      line-height: 1.7;
      color: #1a1a2e;
      background: #f8f9fa;
      padding: 2rem 1rem;
    }
    .wrap {
      max-width: 760px;
      margin: 0 auto;
    }
    .lang-bar {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-bottom: 1.25rem;
    }
    .lang-bar button {
      padding: .35rem .9rem;
      border: 2px solid #d0d5e8;
      border-radius: 6px;
      background: #fff;
      color: #444;
      font-size: .88rem;
      cursor: pointer;
      transition: border-color .15s, background .15s, color .15s;
    }
    .lang-bar button.active,
    .lang-bar button:hover {
      border-color: #3b5bdb;
      background: #3b5bdb;
      color: #fff;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 2.5rem 3rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.07);
    }
    .lang-section { display: none; }
    .lang-section.visible { display: block; }
    h1 { font-size: 1.9rem; margin-bottom: .35rem; color: #111; }
    .updated { color: #888; font-size: .9rem; margin-bottom: 2rem; }
    h2 { font-size: 1.15rem; margin: 2rem 0 .5rem; color: #222; }
    p  { margin-bottom: 1rem; }
    ul { margin: .5rem 0 1rem 1.4rem; }
    ul li { margin-bottom: .35rem; }
    a  { color: #3b5bdb; }
    .contact { background: #f1f3f9; border-radius: 8px; padding: 1rem 1.25rem; margin-top: 2rem; }
    @media (max-width: 600px) {
      .card { padding: 1.5rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="lang-bar">
    <button onclick="switchLang('en')" class="active" id="btn-en">English</button>
    <button onclick="switchLang('de')" id="btn-de">Deutsch</button>
    <button onclick="switchLang('tr')" id="btn-tr">Türkçe</button>
    <button onclick="switchLang('ru')" id="btn-ru">Русский</button>
    <button onclick="switchLang('uk')" id="btn-uk">Українська</button>
  </div>

  <div class="card">

    <!-- ═══════════════ ENGLISH ═══════════════ -->
    <section class="lang-section visible" id="lang-en">
      <h1>Privacy Policy</h1>
      <p class="updated">Last updated: {{ date('F j, Y') }}</p>
      <p>This Privacy Policy describes how the <strong>Booking Saver</strong> Chrome extension ("Extension") collects, uses, and stores information when you use it.</p>

      <h2>1. What data we collect</h2>
      <p>The Extension collects only the data required to save and process hotel booking information:</p>
      <ul>
        <li><strong>Booking details</strong> — hotel name, booking code, dates, guest count, price — parsed from travel agency pages you visit.</li>
        <li><strong>Account credentials</strong> — your username and password are sent to our server solely to authenticate you and obtain a session token. Passwords are never stored in the Extension.</li>
        <li><strong>Authentication token</strong> — stored locally in <code>chrome.storage.local</code> on your device to keep you signed in.</li>
      </ul>
      <p>We do <strong>not</strong> collect browsing history, personal data unrelated to bookings, or any information from pages that are not supported booking sites.</p>

      <h2>2. How we use the data</h2>
      <ul>
        <li>To save booking records to the operator's private database at <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>To send confirmed bookings to the HellOotel reservation system on behalf of the operator.</li>
        <li>To display saved and confirmed bookings in the Extension popup for review.</li>
      </ul>
      <p>We do not sell, rent, or share your data with any third parties except HellOotel (the reservation platform that is the explicit purpose of this tool).</p>

      <h2>3. Data storage</h2>
      <ul>
        <li>Your authentication token is stored locally in <code>chrome.storage.local</code> and never transmitted except as a Bearer header to our API.</li>
        <li>Booking records are stored on our secure server and accessible only to authorised operators.</li>
        <li>HTML snapshots of booking pages ("Page Reports") are automatically deleted after 60 days.</li>
      </ul>

      <h2>4. Permissions used</h2>
      <ul>
        <li><strong>storage</strong> — to store your session token locally.</li>
        <li><strong>activeTab</strong> — to read the current page URL and inject the Save button on supported booking pages.</li>
        <li><strong>Host permissions</strong> (specific domains only) — to communicate with our API and inject the Save button on supported travel agency websites.</li>
      </ul>

      <h2>5. Data security</h2>
      <p>All communication between the Extension and our server is encrypted using HTTPS. Access to booking data is restricted to authenticated users with a valid token.</p>

      <h2>6. Children's privacy</h2>
      <p>The Extension is intended for use by travel industry professionals. It is not directed at children under 13, and we do not knowingly collect data from children.</p>

      <h2>7. Changes to this policy</h2>
      <p>We may update this Privacy Policy from time to time. The "Last updated" date at the top of this page will reflect any changes. Continued use of the Extension after changes constitutes acceptance of the updated policy.</p>

      <div class="contact">
        <strong>Contact</strong><br>
        If you have questions about this Privacy Policy, please contact us at <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ DEUTSCH ═══════════════ -->
    <section class="lang-section" id="lang-de">
      <h1>Datenschutzerklärung</h1>
      <p class="updated">Zuletzt aktualisiert: {{ date('d.m.Y') }}</p>
      <p>Diese Datenschutzerklärung beschreibt, wie die Chrome-Erweiterung <strong>Booking Saver</strong> („Erweiterung") Informationen erfasst, verwendet und speichert.</p>

      <h2>1. Welche Daten wir erheben</h2>
      <p>Die Erweiterung erhebt ausschließlich Daten, die zur Speicherung und Verarbeitung von Hotelbuchungen erforderlich sind:</p>
      <ul>
        <li><strong>Buchungsdetails</strong> — Hotelname, Buchungscode, Reisedaten, Gästezahl, Preis — aus den besuchten Reisebüro-Seiten extrahiert.</li>
        <li><strong>Anmeldedaten</strong> — Benutzername und Passwort werden ausschließlich zur Authentifizierung an unseren Server übertragen und dort nie gespeichert.</li>
        <li><strong>Authentifizierungstoken</strong> — wird lokal in <code>chrome.storage.local</code> gespeichert, um Sie angemeldet zu halten.</li>
      </ul>
      <p>Wir erheben <strong>keinen</strong> Browserverlauf, keine buchungsunabhängigen personenbezogenen Daten und keine Informationen von nicht unterstützten Seiten.</p>

      <h2>2. Wie wir die Daten verwenden</h2>
      <ul>
        <li>Zum Speichern von Buchungsdatensätzen in der privaten Datenbank des Betreibers unter <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Zum Übermitteln bestätigter Buchungen an das HellOotel-Reservierungssystem im Auftrag des Betreibers.</li>
        <li>Zur Anzeige gespeicherter und bestätigter Buchungen im Erweiterungs-Popup.</li>
      </ul>
      <p>Wir verkaufen, vermieten oder teilen Ihre Daten nicht mit Dritten, außer mit HellOotel (dem Reservierungssystem, das der ausdrückliche Zweck dieses Tools ist).</p>

      <h2>3. Datenspeicherung</h2>
      <ul>
        <li>Ihr Authentifizierungstoken wird lokal in <code>chrome.storage.local</code> gespeichert und nur als Bearer-Header an unsere API übertragen.</li>
        <li>Buchungsdatensätze werden auf unserem sicheren Server gespeichert und sind nur autorisierten Betreibern zugänglich.</li>
        <li>HTML-Schnappschüsse von Buchungsseiten („Seitenberichte") werden nach 60 Tagen automatisch gelöscht.</li>
      </ul>

      <h2>4. Verwendete Berechtigungen</h2>
      <ul>
        <li><strong>storage</strong> — zur lokalen Speicherung des Sitzungstokens.</li>
        <li><strong>activeTab</strong> — zum Lesen der aktuellen Seiten-URL und Einfügen der Speichern-Schaltfläche.</li>
        <li><strong>Host-Berechtigungen</strong> (nur bestimmte Domains) — zur Kommunikation mit unserer API und zur Einbindung der Schaltfläche auf unterstützten Reisebüro-Seiten.</li>
      </ul>

      <h2>5. Datensicherheit</h2>
      <p>Die gesamte Kommunikation zwischen der Erweiterung und unserem Server ist durch HTTPS verschlüsselt. Der Zugriff auf Buchungsdaten ist auf authentifizierte Nutzer mit gültigem Token beschränkt.</p>

      <h2>6. Datenschutz für Kinder</h2>
      <p>Die Erweiterung richtet sich an Fachleute der Reisebranche. Sie ist nicht für Kinder unter 13 Jahren bestimmt, und wir erheben wissentlich keine Daten von Kindern.</p>

      <h2>7. Änderungen dieser Richtlinie</h2>
      <p>Wir können diese Datenschutzerklärung von Zeit zu Zeit aktualisieren. Das Datum „Zuletzt aktualisiert" oben auf dieser Seite wird alle Änderungen widerspiegeln. Die weitere Nutzung der Erweiterung nach Änderungen gilt als Zustimmung zur aktualisierten Richtlinie.</p>

      <div class="contact">
        <strong>Kontakt</strong><br>
        Bei Fragen zu dieser Datenschutzerklärung kontaktieren Sie uns bitte unter <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ TÜRKÇE ═══════════════ -->
    <section class="lang-section" id="lang-tr">
      <h1>Gizlilik Politikası</h1>
      <p class="updated">Son güncelleme: {{ date('d.m.Y') }}</p>
      <p>Bu Gizlilik Politikası, <strong>Booking Saver</strong> Chrome uzantısının ("Uzantı") kullanım sırasında hangi bilgileri topladığını, kullandığını ve sakladığını açıklamaktadır.</p>

      <h2>1. Hangi verileri topluyoruz</h2>
      <p>Uzantı yalnızca otel rezervasyonlarını kaydetmek ve işlemek için gereken verileri toplar:</p>
      <ul>
        <li><strong>Rezervasyon bilgileri</strong> — otel adı, rezervasyon kodu, tarihler, misafir sayısı, fiyat — ziyaret ettiğiniz seyahat acentesi sayfalarından alınır.</li>
        <li><strong>Hesap bilgileri</strong> — kullanıcı adı ve şifre yalnızca kimlik doğrulama amacıyla sunucumuza gönderilir; şifreler Uzantı'da hiçbir zaman saklanmaz.</li>
        <li><strong>Kimlik doğrulama jetonu</strong> — oturumunuzu açık tutmak için cihazınızdaki <code>chrome.storage.local</code> alanında yerel olarak saklanır.</li>
      </ul>
      <p>Tarama geçmişi, rezervasyonlarla ilgisi olmayan kişisel veriler veya desteklenmeyen sayfalardaki bilgiler <strong>toplanmaz</strong>.</p>

      <h2>2. Verileri nasıl kullanıyoruz</h2>
      <ul>
        <li>Rezervasyon kayıtlarını operatörün <strong>booking-configurator.hellootel.com</strong> adresindeki özel veritabanına kaydetmek için.</li>
        <li>Onaylanan rezervasyonları operatör adına HellOotel rezervasyon sistemine iletmek için.</li>
        <li>Kaydedilen ve onaylanan rezervasyonları Uzantı açılır penceresinde görüntülemek için.</li>
      </ul>
      <p>Verilerinizi HellOotel (bu aracın açık amacı olan rezervasyon platformu) dışında hiçbir üçüncü tarafla satmıyor, kiralamıyor veya paylaşmıyoruz.</p>

      <h2>3. Veri depolama</h2>
      <ul>
        <li>Kimlik doğrulama jetonunuz yerel olarak <code>chrome.storage.local</code>'da saklanır ve yalnızca API'mize Bearer başlığı olarak iletilir.</li>
        <li>Rezervasyon kayıtları güvenli sunucumuzda saklanır ve yalnızca yetkili operatörler tarafından erişilebilir.</li>
        <li>Rezervasyon sayfalarının HTML anlık görüntüleri ("Sayfa Raporları") 60 gün sonra otomatik olarak silinir.</li>
      </ul>

      <h2>4. Kullanılan izinler</h2>
      <ul>
        <li><strong>storage</strong> — oturum jetonunu yerel olarak saklamak için.</li>
        <li><strong>activeTab</strong> — mevcut sayfa URL'sini okumak ve Kaydet düğmesini eklemek için.</li>
        <li><strong>Ana bilgisayar izinleri</strong> (yalnızca belirli alan adları) — API'mizle iletişim kurmak ve desteklenen seyahat acentesi sitelerinde düğmeyi göstermek için.</li>
      </ul>

      <h2>5. Veri güvenliği</h2>
      <p>Uzantı ile sunucumuz arasındaki tüm iletişim HTTPS ile şifrelenmektedir. Rezervasyon verilerine erişim yalnızca geçerli jetona sahip kimliği doğrulanmış kullanıcılarla sınırlıdır.</p>

      <h2>6. Çocukların gizliliği</h2>
      <p>Uzantı, seyahat sektörü profesyonelleri için tasarlanmıştır. 13 yaşın altındaki çocuklara yönelik değildir ve çocuklardan bilerek veri toplamayız.</p>

      <h2>7. Bu politikadaki değişiklikler</h2>
      <p>Bu Gizlilik Politikasını zaman zaman güncelleyebiliriz. Sayfanın üstündeki "Son güncelleme" tarihi değişiklikleri yansıtacaktır. Değişikliklerden sonra Uzantı'yı kullanmaya devam etmek, güncellenmiş politikayı kabul ettiğiniz anlamına gelir.</p>

      <div class="contact">
        <strong>İletişim</strong><br>
        Bu Gizlilik Politikası hakkında sorularınız için lütfen <a href="mailto:info@hellootel.app">info@hellootel.app</a> adresine yazın.
      </div>
    </section>

    <!-- ═══════════════ РУССКИЙ ═══════════════ -->
    <section class="lang-section" id="lang-ru">
      <h1>Политика конфиденциальности</h1>
      <p class="updated">Последнее обновление: {{ date('d.m.Y') }}</p>
      <p>Настоящая Политика конфиденциальности описывает, какие данные собирает, использует и хранит расширение Chrome <strong>Booking Saver</strong> («Расширение») при его использовании.</p>

      <h2>1. Какие данные мы собираем</h2>
      <p>Расширение собирает только данные, необходимые для сохранения и обработки бронирований отелей:</p>
      <ul>
        <li><strong>Данные о бронировании</strong> — название отеля, код бронирования, даты, количество гостей, цена — извлекаются со страниц посещаемых вами туристических агентств.</li>
        <li><strong>Учётные данные</strong> — логин и пароль передаются на наш сервер исключительно для аутентификации и получения токена сессии. Пароли в Расширении не хранятся.</li>
        <li><strong>Токен аутентификации</strong> — хранится локально в <code>chrome.storage.local</code> на вашем устройстве для поддержания сессии.</li>
      </ul>
      <p>Мы <strong>не</strong> собираем историю браузера, персональные данные, не связанные с бронированиями, и любую информацию со страниц, которые не являются поддерживаемыми сайтами.</p>

      <h2>2. Как мы используем данные</h2>
      <ul>
        <li>Для сохранения записей о бронированиях в частной базе данных оператора на <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Для передачи подтверждённых бронирований в систему резервирования HellOotel от имени оператора.</li>
        <li>Для отображения сохранённых и подтверждённых бронирований в попапе Расширения.</li>
      </ul>
      <p>Мы не продаём, не сдаём в аренду и не передаём ваши данные третьим лицам, кроме HellOotel (платформы резервирования, являющейся прямым назначением этого инструмента).</p>

      <h2>3. Хранение данных</h2>
      <ul>
        <li>Токен аутентификации хранится локально в <code>chrome.storage.local</code> и передаётся только в виде Bearer-заголовка в наш API.</li>
        <li>Записи о бронированиях хранятся на нашем защищённом сервере и доступны только авторизованным операторам.</li>
        <li>HTML-снимки страниц с бронированиями («Отчёты о страницах») автоматически удаляются через 60 дней.</li>
      </ul>

      <h2>4. Используемые разрешения</h2>
      <ul>
        <li><strong>storage</strong> — для локального хранения токена сессии.</li>
        <li><strong>activeTab</strong> — для чтения URL текущей страницы и внедрения кнопки сохранения.</li>
        <li><strong>Разрешения для хостов</strong> (только конкретные домены) — для связи с нашим API и отображения кнопки на поддерживаемых сайтах.</li>
      </ul>

      <h2>5. Безопасность данных</h2>
      <p>Всё взаимодействие между Расширением и нашим сервером зашифровано с использованием HTTPS. Доступ к данным бронирований ограничен аутентифицированными пользователями с действующим токеном.</p>

      <h2>6. Конфиденциальность детей</h2>
      <p>Расширение предназначено для специалистов туристической отрасли. Оно не направлено на детей до 13 лет, и мы намеренно не собираем данные о детях.</p>

      <h2>7. Изменения политики</h2>
      <p>Мы можем периодически обновлять настоящую Политику конфиденциальности. Дата «Последнее обновление» в начале страницы будет отражать любые изменения. Продолжение использования Расширения после изменений означает принятие обновлённой политики.</p>

      <div class="contact">
        <strong>Контакты</strong><br>
        По вопросам, связанным с настоящей Политикой конфиденциальности, обращайтесь по адресу: <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ УКРАЇНСЬКА ═══════════════ -->
    <section class="lang-section" id="lang-uk">
      <h1>Політика конфіденційності</h1>
      <p class="updated">Останнє оновлення: {{ date('d.m.Y') }}</p>
      <p>Ця Політика конфіденційності описує, які дані збирає, використовує та зберігає розширення Chrome <strong>Booking Saver</strong> («Розширення») під час його використання.</p>

      <h2>1. Які дані ми збираємо</h2>
      <p>Розширення збирає лише дані, необхідні для збереження та обробки бронювань готелів:</p>
      <ul>
        <li><strong>Дані бронювання</strong> — назва готелю, код бронювання, дати, кількість гостей, ціна — отримані зі сторінок туристичних агентств, які ви відвідуєте.</li>
        <li><strong>Облікові дані</strong> — логін і пароль передаються на наш сервер виключно для автентифікації та отримання токена сесії. Паролі в Розширенні не зберігаються.</li>
        <li><strong>Токен автентифікації</strong> — зберігається локально в <code>chrome.storage.local</code> на вашому пристрої для підтримки сесії.</li>
      </ul>
      <p>Ми <strong>не</strong> збираємо історію перегляду, персональні дані, не пов'язані з бронюваннями, та будь-яку інформацію зі сторінок, що не є підтримуваними сайтами.</p>

      <h2>2. Як ми використовуємо дані</h2>
      <ul>
        <li>Для збереження записів бронювань у приватній базі даних оператора на <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Для передачі підтверджених бронювань до системи резервування HellOotel від імені оператора.</li>
        <li>Для відображення збережених і підтверджених бронювань у спливаючому вікні Розширення.</li>
      </ul>
      <p>Ми не продаємо, не здаємо в оренду та не передаємо ваші дані третім особам, крім HellOotel (платформи резервування, що є прямим призначенням цього інструменту).</p>

      <h2>3. Зберігання даних</h2>
      <ul>
        <li>Токен автентифікації зберігається локально в <code>chrome.storage.local</code> і передається лише як Bearer-заголовок до нашого API.</li>
        <li>Записи бронювань зберігаються на нашому захищеному сервері та доступні лише авторизованим операторам.</li>
        <li>HTML-знімки сторінок бронювань («Звіти сторінок») автоматично видаляються через 60 днів.</li>
      </ul>

      <h2>4. Використані дозволи</h2>
      <ul>
        <li><strong>storage</strong> — для локального зберігання токена сесії.</li>
        <li><strong>activeTab</strong> — для зчитування URL поточної сторінки та впровадження кнопки збереження.</li>
        <li><strong>Дозволи для хостів</strong> (лише конкретні домени) — для зв'язку з нашим API та відображення кнопки на підтримуваних сайтах.</li>
      </ul>

      <h2>5. Безпека даних</h2>
      <p>Уся взаємодія між Розширенням і нашим сервером зашифрована за допомогою HTTPS. Доступ до даних бронювань обмежений автентифікованими користувачами з дійсним токеном.</p>

      <h2>6. Конфіденційність дітей</h2>
      <p>Розширення призначене для фахівців туристичної галузі. Воно не орієнтоване на дітей до 13 років, і ми свідомо не збираємо дані про дітей.</p>

      <h2>7. Зміни до політики</h2>
      <p>Ми можемо periodically оновлювати цю Політику конфіденційності. Дата «Останнє оновлення» на початку сторінки відображатиме будь-які зміни. Продовження використання Розширення після змін означає прийняття оновленої політики.</p>

      <div class="contact">
        <strong>Контакти</strong><br>
        З питань, пов'язаних із цією Політикою конфіденційності, звертайтесь на адресу: <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

  </div><!-- .card -->
</div><!-- .wrap -->

<script>
  function switchLang(code) {
    document.querySelectorAll('.lang-section').forEach(s => s.classList.remove('visible'));
    document.querySelectorAll('.lang-bar button').forEach(b => b.classList.remove('active'));
    document.getElementById('lang-' + code).classList.add('visible');
    document.getElementById('btn-' + code).classList.add('active');
  }

  // Auto-detect browser language on first load
  (function () {
    const map = { ru: 'ru', uk: 'uk', de: 'de', tr: 'tr', en: 'en' };
    const lang = (navigator.language || 'en').slice(0, 2).toLowerCase();
    if (map[lang] && lang !== 'en') switchLang(map[lang]);
  })();
</script>
</body>
</html>
