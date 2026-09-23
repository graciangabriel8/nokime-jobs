/* Nokime Jobs — the offers. Written in French, as the law wants job offers in France (Code du travail L5331-4).
   One object per offer; the list page renders them, newest first, until `expires`.
   Add an offer here after the restaurant's mail has been checked and its plan paid.

   kind: "stage" | "alternance" | "saison"
   dept: the département number as a string ("74"); the région is derived.
   housing: true when lodging is provided; pay: free text ("gratification légale", "1 900 € brut"), optional:
   empty, missing or with payHidden: true, the offer reads "Non communiquée" and, where the law sets one, the floor for its kind.
   distinction: null until the working-comfort distinction exists.
   en: optional, Nokime's English translation for the English view: { "role", "restaurant", "pay", "text" }, any of them.
   The French stays the original and is shown under it (the same article lets a medium that uses English carry offers in it).
   This file is public: with payHidden, keep neither pay nor en.pay in it.
*/
window.NOKIME_JOBS = [];

/* Shown only with ?demo=1, never in the public list: the shape of an offer, for layout work. */
window.NOKIME_JOBS_DEMO = [
  { id: "demo-1", kind: "saison", restaurant: "Exemple — restaurant d’altitude", city: "Megève", dept: "74", role: "Commis de cuisine",
    start: "2026-12-15", end: "2027-04-12", hours: 42, pay: "1 950 € brut, logé, nourri", housing: true,
    contact: { name: "Le chef", email: "exemple@exemple.invalid", phone: "" },
    text: "Brigade de huit, carte courte, produits de la vallée. Deux jours de repos consécutifs hors vacances scolaires.",
    en: { role: "Commis chef", restaurant: "Example — mountain restaurant", pay: "€1,950 gross, with board and lodging",
          text: "A brigade of eight, a short menu, produce from the valley. Two consecutive days off outside school holidays." },
    published: "2026-09-22", expires: "2026-12-01", distinction: null, demo: true },
  { id: "demo-2", kind: "stage", restaurant: "Exemple — bistrot de quartier", city: "Lyon", dept: "69", role: "Stage en cuisine",
    start: "2027-01-11", end: "2027-03-05", hours: 39, pay: "Gratification légale", housing: false,
    contact: { name: "La cheffe", email: "exemple@exemple.invalid", phone: "" },
    text: "Huit semaines sur tous les postes, du garde-manger au chaud. Convention avec l’école.",
    en: { role: "Kitchen internship", restaurant: "Example — neighbourhood bistro", pay: "Statutory internship allowance",
          text: "Eight weeks on every station, from the larder to the hot line. An internship agreement with the school." },
    published: "2026-09-22", expires: "2027-01-05", distinction: null, demo: true },
  { id: "demo-3", kind: "alternance", restaurant: "Exemple — maison étoilée", city: "Annecy", dept: "74", role: "Apprenti pâtisserie",
    start: "2027-01-04", end: "2028-12-22", hours: 35, housing: false,
    contact: { name: "Le chef pâtissier", email: "exemple@exemple.invalid", phone: "" },
    text: "Deux ans en pâtisserie de restaurant, un mentor, un rythme école-entreprise 1-3.",
    en: { role: "Pastry apprentice", restaurant: "Example — starred restaurant",
          text: "Two years in a restaurant pastry kitchen, a mentor, a 1-3 school/work rhythm." },
    published: "2026-09-22", expires: "2026-10-30", distinction: null, demo: true }
];
