"""The questionnaire, in one place.

tools/build.py reads this file to write the question fieldsets of questionnaire/index.html and
the English strings in js/i18n.js (between the "questions" markers). Change a French label here,
then run python3 tools/build.py.

An id is what the private file stores (reponses.json, outside any repository): never rename an id
once answers use it. Each question: id, kind ("radio": one answer, "check": any number), French
label, English label, options as (id, French, English), in the order they are shown.
"""

QUESTIONS = [
    {"id": "heures", "kind": "radio",
     "fr": "Les heures faites, comparées à la convention ou au contrat",
     "en": "The hours worked, compared with the agreement or the contract",
     "options": [
         ("prevu", "Comme prévu", "As planned"),
         ("plus", "Un peu plus (jusqu’à 5 h par semaine)", "A little more (up to 5 h a week)"),
         ("beaucoup_plus", "Beaucoup plus (plus de 5 h par semaine)", "Much more (over 5 h a week)"),
         ("moins", "Moins", "Less"),
     ]},
    {"id": "pauses", "kind": "radio",
     "fr": "Les pauses", "en": "The breaks",
     "options": [("oui", "Oui", "Yes"), ("souvent", "La plupart du temps", "Most of the time"), ("non", "Non", "No")]},
    {"id": "repos", "kind": "radio",
     "fr": "Les jours de repos prévus", "en": "The planned days off",
     "options": [("oui", "Oui", "Yes"), ("souvent", "La plupart du temps", "Most of the time"), ("non", "Non", "No")]},
    {"id": "paiement", "kind": "radio",
     "fr": "La gratification ou le salaire, versé à temps", "en": "The allowance or the salary, paid on time",
     "options": [("oui", "Oui", "Yes"), ("non", "Non", "No"), ("pas_du", "Pas dû", "Not due")]},
    {"id": "appris", "kind": "check",
     "fr": "Ce que j’ai appris", "en": "What I learned",
     "options": [
         ("bases", "Les bases et la mise en place", "The basics and the mise en place"),
         ("cuissons", "Les cuissons", "Cooking"),
         ("patisserie", "La pâtisserie", "Pastry"),
         ("dressage", "Le dressage", "Plating"),
         ("passe", "Le travail au passe", "Working the pass"),
         ("hygiene", "L’hygiène", "Hygiene"),
         ("commandes", "Les commandes et les stocks", "Orders and stock"),
     ]},
    {"id": "traite", "kind": "radio",
     "fr": "Je me suis senti·e bien traité·e", "en": "I felt well treated",
     "options": [("oui", "Oui", "Yes"), ("plutot_oui", "Plutôt oui", "Mostly yes"), ("plutot_non", "Plutôt non", "Mostly no"), ("non", "Non", "No")]},
    {"id": "revenir", "kind": "radio",
     "fr": "J’y retournerais", "en": "I would go back",
     "options": [("oui", "Oui", "Yes"), ("non", "Non", "No")]},
]

# the month selects of the period: (French, English)
MOIS = [("janvier", "January"), ("février", "February"), ("mars", "March"), ("avril", "April"), ("mai", "May"), ("juin", "June"),
        ("juillet", "July"), ("août", "August"), ("septembre", "September"), ("octobre", "October"), ("novembre", "November"), ("décembre", "December")]


def key(qid, oid=None):
    """The data-t key of a question, or of one of its options."""
    return "rq_%s" % qid if oid is None else "rq_%s_%s" % (qid, oid)
