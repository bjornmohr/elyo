export interface LabMarkerExplanation {
  markerKey: string;
  title: string;
  shortExplanation: string;
  contextNote: string;
  safetyNote: string;
}

// TODO: Phase 1 keeps marker explanations in a centralized frontend catalog. Later this should move to a versioned backend marker catalog with fachliche Freigabe.
export const LAB_MARKER_EXPLANATIONS: Record<string, LabMarkerExplanation> = {
  vitd: {
    markerKey: 'vitd',
    title: 'Was beschreibt Vitamin D?',
    shortExplanation: 'Vitamin D wird häufig im Zusammenhang mit Knochenstoffwechsel und allgemeiner Versorgung betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von Labor, Jahreszeit, Tageslicht, Ernährung und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  ferritin: {
    markerKey: 'ferritin',
    title: 'Was beschreibt Ferritin?',
    shortExplanation: 'Ferritin wird häufig als Speicherform von Eisen betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Entzündungssignalen und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  crp: {
    markerKey: 'crp',
    title: 'Was beschreibt CRP?',
    shortExplanation: 'CRP ist ein unspezifisches Entzündungssignal.',
    contextNote: 'Die Einordnung sollte immer im Kontext von Beschwerden, Verlauf und weiteren Werten betrachtet werden.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  hb: {
    markerKey: 'hb',
    title: 'Was beschreibt Hämoglobin?',
    shortExplanation: 'Hämoglobin ist ein Bestandteil der roten Blutkörperchen und wird häufig im Zusammenhang mit Sauerstofftransport betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Referenzbereich und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  hkt: {
    markerKey: 'hkt',
    title: 'Was beschreibt Hämatokrit?',
    shortExplanation: 'Hämatokrit beschreibt den Anteil der Blutzellen am Blutvolumen.',
    contextNote: 'Die Einordnung hängt unter anderem von Flüssigkeitshaushalt, Blutbild und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  ery: {
    markerKey: 'ery',
    title: 'Was beschreiben Erythrozyten?',
    shortExplanation: 'Erythrozyten sind rote Blutkörperchen und werden häufig im Zusammenhang mit Sauerstofftransport betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von weiteren Blutbildwerten und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  mcv: {
    markerKey: 'mcv',
    title: 'Was beschreibt MCV?',
    shortExplanation: 'MCV beschreibt die durchschnittliche Größe der roten Blutkörperchen.',
    contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  mch: {
    markerKey: 'mch',
    title: 'Was beschreibt MCH?',
    shortExplanation: 'MCH beschreibt die durchschnittliche Menge an Hämoglobin pro rotem Blutkörperchen.',
    contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  mchc: {
    markerKey: 'mchc',
    title: 'Was beschreibt MCHC?',
    shortExplanation: 'MCHC beschreibt die durchschnittliche Hämoglobin-Konzentration in den roten Blutkörperchen.',
    contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  rdw: {
    markerKey: 'rdw',
    title: 'Was beschreibt RDW?',
    shortExplanation: 'RDW beschreibt, wie stark sich rote Blutkörperchen in ihrer Größe unterscheiden.',
    contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  leuko: {
    markerKey: 'leuko',
    title: 'Was beschreiben Leukozyten?',
    shortExplanation: 'Leukozyten sind weiße Blutkörperchen und werden häufig im Zusammenhang mit Immunaktivität betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von Verlauf, Beschwerden und weiteren Werten ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
  thrombo: {
    markerKey: 'thrombo',
    title: 'Was beschreiben Thrombozyten?',
    shortExplanation: 'Thrombozyten sind Blutplättchen und werden häufig im Zusammenhang mit Blutgerinnung betrachtet.',
    contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Verlauf und individuellem Kontext ab.',
    safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.',
  },
};
