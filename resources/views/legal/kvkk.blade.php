<x-layouts.customer title="KVKK Aydınlatma Metni">

    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <p class="section-label">Kişisel Verilerin Korunması</p>
            <h1 class="page-title mt-1">Aydınlatma Metni</h1>
            <p class="page-subtitle">6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca</p>
        </div>

        <div class="surface space-y-6 p-6 text-sm leading-relaxed text-ink sm:p-8">
            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">1. Veri Sorumlusu</h2>
                <p class="text-ink-muted">
                    Sigorta Eğitim, Dinlenme ve Sosyal Tesisler Derneği ("Dernek"), Çolaklı ve Güre
                    tesislerine ilişkin rezervasyon hizmetleri kapsamında kişisel verilerinizi veri
                    sorumlusu sıfatıyla işlemektedir.
                </p>
            </section>

            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">2. İşlenen Kişisel Veriler</h2>
                <ul class="list-disc space-y-1 pl-5 text-ink-muted">
                    <li><strong class="text-ink">Kimlik bilgileri:</strong> ad soyad, TC kimlik numarası, doğum tarihi, kimlik belgesi görüntüsü, vukuatlı nüfus kayıt örneği</li>
                    <li><strong class="text-ink">İletişim bilgileri:</strong> telefon, e-posta, adres</li>
                    <li><strong class="text-ink">Üyelik bilgileri:</strong> üyelik numarası, aidat kayıtları</li>
                    <li><strong class="text-ink">Konaklama bilgileri:</strong> rezervasyon geçmişi, birlikte konaklayan kişiler ve yakınlık dereceleri</li>
                    <li><strong class="text-ink">Finansal bilgiler:</strong> ödeme kayıtları, banka dekontları, iade işlemleri için IBAN</li>
                    <li><strong class="text-ink">Sağlık bilgileri:</strong> yalnızca alt kat/zemin kat talebine dayanak sağlık raporu (açık rızanızla)</li>
                </ul>
            </section>

            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">3. İşleme Amaçları ve Hukuki Sebep</h2>
                <p class="text-ink-muted">
                    Verileriniz; rezervasyon başvurularının alınması ve değerlendirilmesi, konaklama
                    hakkının Kamp Konaklama Usul ve Esasları'na göre doğrulanması (yakınlık ve müşteri
                    grubu tespiti), ücretlendirme ve tahsilat, iade işlemleri, aidat takibi ve yasal
                    yükümlülüklerin yerine getirilmesi amaçlarıyla; KVKK m.5/2 uyarınca sözleşmenin
                    kurulması ve ifası, hukuki yükümlülük ve meşru menfaat hukuki sebeplerine dayanılarak
                    işlenir. Sağlık raporu yalnızca açık rızanıza dayanılarak işlenir.
                </p>
            </section>

            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">4. Aktarım</h2>
                <p class="text-ink-muted">
                    Kart ödemelerinde ödeme bilgileriniz bankanın güvenli ödeme sayfası üzerinden işlenir;
                    kart bilgileriniz Dernek sistemlerinde saklanmaz. Verileriniz, yasal zorunluluklar
                    dışında üçüncü kişilerle paylaşılmaz.
                </p>
            </section>

            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">5. Saklama Süresi</h2>
                <p class="text-ink-muted">
                    Kişisel verileriniz, üyelik ilişkisi süresince ve ilgili mevzuattaki zamanaşımı
                    süreleri boyunca saklanır; süre sonunda silinir, yok edilir veya anonim hâle getirilir.
                    Kimlik belgeleri ve nüfus kayıt örnekleri yalnızca yetkili personelce görüntülenebilir.
                </p>
            </section>

            <section>
                <h2 class="mb-2 font-display text-base font-semibold text-ink">6. KVKK m.11 Kapsamındaki Haklarınız</h2>
                <p class="text-ink-muted">
                    Verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, düzeltilmesini veya
                    silinmesini isteme, aktarıldığı üçüncü kişileri bilme, otomatik sistemlerce analiz
                    sonucu aleyhinize çıkan sonuçlara itiraz etme ve zarara uğramanız hâlinde tazminat
                    talep etme haklarına sahipsiniz. Taleplerinizi panelinizdeki
                    <a href="{{ auth()->check() && auth()->user()->role === 'customer' ? route('customer.petitions.index') : route('login') }}" class="font-medium text-accent-600 underline dark:text-accent-400">Dilekçelerim</a>
                    sayfasından veya Dernek iletişim kanallarından iletebilirsiniz.
                </p>
            </section>
        </div>
    </div>
</x-layouts.customer>
