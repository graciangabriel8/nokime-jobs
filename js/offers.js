/* Nokime Jobs — the offers. Written in French, as the law wants job offers in France (Code du travail L5331-4).
   One object per offer; the list page renders them, newest first, until `expires`.
   Live offers are strict JSON: every key in double quotes ("id": "…"), unlike the demo list below; tools/build.py stops otherwise.
   Add an offer here after the restaurant's mail has been checked (and, after its three free months, its plan paid).

   kind: "stage" | "alternance" | "saison"
   metier: "cuisine" | "salle" | "hebergement" | "spa", the department (Cuisine / Salle / Hébergement / Spa; in English Kitchen / Dining room /
   Rooms division / Spa; "spa" is a hotel's spa: the board takes no beauty salon or day spa outside a hotel),
   shown beside the kind and filtered by the board's métier select; absent, no department is shown and the offer drops out only
   when a department is selected.
   dept: the département number as a string ("74"); the région is derived.
   housing: true when lodging is provided, false when not (the offer reads "Non logé"), absent and nothing is shown; pay: free text ("gratification légale", "1 900 € brut"), optional:
   empty, missing or with payHidden: true, the offer reads "Non communiquée" and, where the law sets one, the floor for its kind.
   distinction: null until the working-comfort distinction exists.
   boost: true when the restaurant paid for "Mise en avant"; honoured only when the offer states its pay (not payHidden), its hours
   and housing (true or false), and only with charter: true (the establishment signed the charte d’accueil, charte/) and without
   boostPaused: true (set while a serious report about the establishment is checked). Up to three such offers come first on the
   board, the most recently published, with a label.
   en: optional, Nokime's English translation for the English view: { "role", "restaurant", "pay", "text" }, any of them.
   The French stays the original and is shown under it (the same article lets a medium that uses English carry offers in it).
   This file is public: with payHidden, keep neither pay nor en.pay in it.
*/
window.NOKIME_JOBS = [];

/* Shown only with ?demo=1, never in the public list: the shape of an offer, for layout work. */
window.NOKIME_JOBS_DEMO = [
  { id: "demo-1", metier: "cuisine", kind: "saison", restaurant: "Exemple — restaurant d’altitude", city: "Megève", dept: "74", role: "Commis de cuisine",
    start: "2026-12-15", end: "2027-04-12", hours: 42, pay: "1 950 € brut, logé, nourri", housing: true,
    contact: { name: "Le chef", email: "exemple@exemple.invalid", phone: "" },
    text: "Brigade de huit, carte courte, produits de la vallée. Deux jours de repos consécutifs hors vacances scolaires.",
    en: { role: "Commis chef", restaurant: "Example — mountain restaurant", pay: "€1,950 gross, with board and lodging",
          text: "A brigade of eight, a short menu, produce from the valley. Two consecutive days off outside school holidays." },
    published: "2026-09-22", expires: "2026-12-01", distinction: null, demo: true },
  { id: "demo-2", metier: "cuisine", kind: "stage", restaurant: "Exemple — bistrot de quartier", city: "Lyon", dept: "69", role: "Stage en cuisine",
    start: "2027-01-11", end: "2027-03-05", hours: 39, pay: "Gratification légale", housing: false,
    contact: { name: "La cheffe", email: "exemple@exemple.invalid", phone: "" },
    text: "Huit semaines sur tous les postes, du garde-manger au chaud. Convention avec l’école.",
    en: { role: "Kitchen internship", restaurant: "Example — neighbourhood bistro", pay: "Statutory internship allowance",
          text: "Eight weeks on every station, from the larder to the hot line. An internship agreement with the school." },
    published: "2026-09-22", expires: "2027-01-05", distinction: null, demo: true },
  { id: "demo-3", metier: "cuisine", kind: "alternance", restaurant: "Exemple — maison étoilée", city: "Annecy", dept: "74", role: "Apprenti pâtisserie",
    start: "2027-01-04", end: "2028-12-22", hours: 35, housing: false,
    contact: { name: "Le chef pâtissier", email: "exemple@exemple.invalid", phone: "" },
    text: "Deux ans en pâtisserie de restaurant, un mentor, un rythme école-entreprise 1-3.",
    en: { role: "Pastry apprentice", restaurant: "Example — starred restaurant",
          text: "Two years in a restaurant pastry kitchen, a mentor, a 1-3 school/work rhythm." },
    published: "2026-09-22", expires: "2026-10-30", distinction: null, demo: true },
  { id: "demo-4", metier: "cuisine", kind: "saison", restaurant: "Exemple — hôtel-restaurant de station", city: "Megève", dept: "74", role: "Chef de partie",
    start: "2026-12-12", end: "2027-04-18", hours: 39, pay: "2 250 € brut, logé, nourri", housing: true, boost: true, charter: true,
    contact: { name: "Le second", email: "exemple@exemple.invalid", phone: "" },
    text: "Poste au chaud, service midi et soir, deux jours de repos par semaine. Studio individuel à cinq minutes à pied.",
    en: { role: "Chef de partie", restaurant: "Example — resort hotel restaurant", pay: "€2,250 gross, with board and lodging",
          text: "Hot station, lunch and dinner service, two days off a week. A studio of your own, five minutes on foot." },
    published: "2026-09-15", expires: "2026-11-25", distinction: null, demo: true },
  { id: "demo-5", metier: "cuisine", kind: "stage", restaurant: "Exemple — table de quartier", city: "Saint-Étienne", dept: "42", role: "Stage en pâtisserie",
    start: "2027-01-18", end: "2027-03-26", hours: 35, pay: "Gratification légale, repas fournis", housing: true, boost: true, charter: true,
    contact: { name: "La cheffe pâtissière", email: "exemple@exemple.invalid", phone: "" },
    text: "Dix semaines aux desserts de l’assiette et au goûter du dimanche. Chambre dans l’appartement au-dessus du restaurant.",
    en: { role: "Pastry internship", restaurant: "Example — neighbourhood restaurant", pay: "Statutory internship allowance, meals provided",
          text: "Ten weeks on plated desserts and the Sunday afternoon tea. A room in the flat above the restaurant." },
    published: "2026-09-19", expires: "2027-01-10", distinction: null, demo: true },
  { id: "demo-6", metier: "cuisine", kind: "alternance", restaurant: "Exemple — auberge de village", city: "Montbrison", dept: "42", role: "Apprenti cuisinier",
    start: "2027-01-04", end: "2028-08-31", hours: 35, payHidden: true, housing: false, boost: true, charter: true,
    contact: { name: "Le chef", email: "exemple@exemple.invalid", phone: "" },
    text: "CAP cuisine en deux ans, une brigade de trois, les légumes du Forez.",
    en: { role: "Apprentice cook", restaurant: "Example — village inn",
          text: "A two-year cookery CAP, a brigade of three, vegetables from the Forez." },
    published: "2026-09-21", expires: "2026-12-15", distinction: null, demo: true },
  /* boosted and transparent, but the charter is not signed: its normal place, no label */
  { id: "demo-7", metier: "cuisine", kind: "saison", restaurant: "Exemple — brasserie de bord de mer", city: "Arcachon", dept: "33", role: "Commis de cuisine",
    start: "2027-04-05", end: "2027-09-30", hours: 39, pay: "1 900 € brut", housing: false, boost: true,
    contact: { name: "Le chef", email: "exemple@exemple.invalid", phone: "" },
    text: "Service midi et soir, poissons de la criée, deux jours de repos par semaine.",
    en: { role: "Commis chef", restaurant: "Example — seaside brasserie", pay: "€1,900 gross",
          text: "Lunch and dinner service, fish from the auction, two days off a week." },
    published: "2026-09-20", expires: "2027-03-15", distinction: null, demo: true },
  /* boosted, transparent, charter signed, but paused while a report is checked: its normal place, no label */
  { id: "demo-8", metier: "cuisine", kind: "alternance", restaurant: "Exemple — buffet de gare", city: "Tours", dept: "37", role: "Apprenti commis",
    start: "2027-01-04", end: "2028-12-22", hours: 35, pay: "53 % du SMIC la première année", housing: false, boost: true, charter: true, boostPaused: true,
    contact: { name: "La cheffe", email: "exemple@exemple.invalid", phone: "" },
    text: "CAP cuisine en deux ans, une cuisine de produits de Touraine, service du midi.",
    en: { role: "Apprentice commis", restaurant: "Example — station buffet", pay: "53% of the minimum wage in the first year",
          text: "A two-year cookery CAP, a kitchen of Touraine produce, lunch service." },
    published: "2026-09-18", expires: "2026-12-15", distinction: null, demo: true },
  /* the dining room, the rooms division and the spa */
  { id: "demo-9", metier: "salle", kind: "saison", restaurant: "Exemple — restaurant d’un hôtel au bord du lac", city: "Évian-les-Bains", dept: "74", role: "Chef de rang",
    start: "2027-04-26", end: "2027-10-03", hours: 39, pay: "2 050 € brut, logé, nourri", housing: true,
    contact: { name: "Le maître d’hôtel", email: "exemple@exemple.invalid", phone: "" },
    text: "Soixante couverts, service midi et soir, une terrasse sur le lac. Anglais courant apprécié. Deux jours de repos par semaine, chambre individuelle dans la résidence du personnel.",
    en: { role: "Chef de rang (station waiter)", restaurant: "Example — lakeside hotel restaurant", pay: "€2,050 gross, with board and lodging",
          text: "Sixty covers, lunch and dinner service, a terrace on the lake. Fluent English appreciated. Two days off a week, a room of your own in the staff residence." },
    published: "2026-09-23", expires: "2027-03-31", distinction: null, demo: true },
  { id: "demo-10", metier: "hebergement", kind: "alternance", restaurant: "Exemple — hôtel de centre-ville", city: "Lyon", dept: "69", role: "Réceptionniste",
    start: "2027-01-04", end: "2028-06-30", hours: 35, housing: false,
    contact: { name: "La cheffe de réception", email: "exemple@exemple.invalid", phone: "" },
    text: "BTS MHR option hébergement en alternance : l’accueil, les arrivées et les départs, les réservations, puis l’audit de nuit en deuxième année. Quatre-vingts chambres, une tutrice à la réception.",
    en: { role: "Receptionist", restaurant: "Example — city-centre hotel",
          text: "A BTS MHR (rooms division option) as an apprenticeship: welcoming guests, arrivals and departures, reservations, then the night audit in the second year. Eighty rooms, a tutor at the front desk." },
    published: "2026-09-23", expires: "2026-12-15", distinction: null, demo: true },
  { id: "demo-11", metier: "spa", kind: "saison", restaurant: "Exemple — spa d’un hôtel de montagne", city: "Courchevel", dept: "73", role: "Praticien·ne spa",
    start: "2026-12-12", end: "2027-04-18", hours: 39, pay: "1 950 € brut, logé, nourri", housing: true,
    contact: { name: "La responsable du spa", email: "exemple@exemple.invalid", phone: "" },
    text: "Soins du visage et du corps, modelages, accueil des clients de l’hôtel au spa : CAP ou BP esthétique demandé. Six cabines, une piscine et un hammam. Deux jours de repos par semaine, chambre individuelle dans la résidence du personnel.",
    en: { role: "Spa therapist", restaurant: "Example — mountain hotel spa", pay: "€1,950 gross, with board and lodging",
          text: "Face and body treatments, massages, welcoming the hotel’s guests at the spa: a CAP or BP in beauty therapy required. Six treatment rooms, a pool and a hammam. Two days off a week, a room of your own in the staff residence." },
    published: "2026-09-23", expires: "2026-12-01", distinction: null, demo: true }
];
