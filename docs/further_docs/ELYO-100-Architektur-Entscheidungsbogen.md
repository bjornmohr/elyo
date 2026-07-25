# ELYO-100 – Architektur-Entscheidungsbogen

## Zweck

Diese Datei ist die zentrale Arbeitsgrundlage für die Architekturentscheidung zur Trennung von Identity-, Mapping-, Health- und Reporting-Daten.

Sie dient dazu,

- offene Fragen strukturiert zu beantworten,
- Entscheidungen nachvollziehbar zu dokumentieren,
- stabile Referenzen für spätere Diskussionen zu behalten,
- die spätere ADR-Erstellung vorzubereiten,
- bewusst vertagte Themen von tatsächlich offenen Punkten zu unterscheiden.

## Verwendung

Jeder Punkt besitzt eine feste Referenz-ID wie `1.1`, `5.2` oder `11.4`.

Bitte pro Punkt möglichst diese Felder pflegen:

- **Status:** `DECIDED`, `OPEN`, `DEFERRED`, `OUT_OF_SCOPE`, `PARTIALLY_DECIDED`
- **Entscheidung / Antwort:** Die tatsächliche Festlegung
- **Begründung:** Warum diese Entscheidung getroffen wurde
- **Folgeaufgabe:** Falls daraus ein späteres Ticket oder eine Detailentscheidung entsteht
- **ADR-relevant:** `JA` oder `NEIN`

Die Referenz-IDs bleiben stabil und sollen künftig nicht neu nummeriert werden.

---

# 0. Bereits festgelegte Architekturgrundlagen

## 0.1 Physische Datenbanktrennung

- **Status:** DECIDED
- **Entscheidung / Antwort:** Vier getrennte PostgreSQL-Datenbanken auf einem gemeinsamen Cluster:
  - `elyo_identity`
  - `elyo_subject_mapping`
  - `elyo_health`
  - `elyo_reporting`
- **Begründung:** Stärkere technische Trennung als reine Schemas, ohne den Betriebsaufwand eines verteilten Servicesystems.
- **Folgeaufgabe:** Datenbank- und Rollenmatrix erstellen.
- **ADR-relevant:** JA

## 0.2 Runtime-Modell

- **Status:** DECIDED
- **Entscheidung / Antwort:** Gemeinsamer Codebestand mit getrennten Runtime-Prozessen beziehungsweise Startprofilen.
- **Mindestens vorgesehene Runtimes:**
  - Identity API
  - Employee Health API
  - Company API
  - Reporting Worker
- **Begründung:** Company- und Health-Kontext sollen technisch unterschiedliche Credentials und Berechtigungen besitzen.
- **Folgeaufgabe:** Exakte Runtime-Grenzen unter Punkt 6.1 festlegen.
- **ADR-relevant:** JA

## 0.3 Health Subject

- **Status:** DECIDED
- **Entscheidung / Antwort:** Ein globales, arbeitgeberunabhängiges `health_subject_id`.
- **Begründung:** Der persönliche Gesundheitsverlauf bleibt auch bei Arbeitgeberwechseln konsistent.
- **Folgeaufgabe:** Lifecycle, Provisionierung und Löschung konkretisieren.
- **ADR-relevant:** JA

## 0.4 Mapping-Grundschutz

- **Status:** DECIDED
- **Entscheidung / Antwort:** Kein freier ORM-Zugriff außerhalb der Mapping-Domäne. Mapping nur über zweckgebundene Anwendungsoperationen.
- **Begründung:** Ein bloßer ID-Austausch ohne technische Zugriffsbeschränkung schützt nicht vor trivialen Joins.
- **Folgeaufgabe:** Mapping-Contract definieren.
- **ADR-relevant:** JA

## 0.5 Reporting-Grundsatz

- **Status:** DECIDED
- **Entscheidung / Antwort:** Reporting speichert ausschließlich suppressionsgeprüfte Aggregate, keine `user_id` und keine `health_subject_id`.
- **Begründung:** Arbeitgeber erhalten keine individuellen Gesundheitsdaten.
- **Folgeaufgabe:** Suppression und Kohortenbildung konkretisieren.
- **ADR-relevant:** JA

## 0.6 Produktives Laborwertmodell

- **Status:** DECIDED
- **Entscheidung / Antwort:** Das produktive Laborwertmodell wird vollständig neu entworfen. Das Demo-Schema wird nicht fortgeführt.
- **Begründung:** Demo-Strukturen sind kein belastbares fachliches oder regulatorisches Zielmodell.
- **Folgeaufgabe:** Eigenes Folgeticket für das Laborwert-Domänenmodell.
- **ADR-relevant:** JA

---

# 1. Identität, Registrierung und E-Mail

## 1.1 Primäre Account-E-Mail

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Die Einladung wird an die vom Arbeitgeber angegebene Adresse versendet.
  - Der Nutzer weist über den Einladungsprozess den Zugriff auf diesen Einladungskanal nach.
  - Der Nutzer darf anschließend eine eigene verifizierte primäre Account-E-Mail angeben.
  - Die primäre Account-E-Mail ist die dauerhaft vom Nutzer kontrollierte Login- und Recovery-Adresse.
  - Die Arbeitgeberadresse bleibt ein Attribut der Einladung beziehungsweise Membership und wird nicht zur dauerhaften Identität des Kontos.
- **Begründung:** Der Account und das globale Health Subject dürfen nicht dauerhaft von einer Arbeitgeberadresse abhängen.
- **Folgeaufgabe:** Registrierungskontrakt und Datenmodell für E-Mail-Verifikation definieren.
- **ADR-relevant:** JA

## 1.2 Änderung der primären E-Mail-Adresse

- **Status:** DEFERRED
- **Entscheidung / Antwort:** Für den Pilot zunächst kein vollständiger Self-Service-Prozess. Ein Support-Admin darf auf bestätigte Anfrage eine Änderung durchführen.
- **Begründung:** Der vollständige Recovery- und Sicherheitsprozess wird für den Pilot bewusst vertagt.
- **Noch später zu klären:**
  - Identitätsprüfung
  - erneute Authentifizierung
  - Benachrichtigung der alten Adresse
  - Session-Widerruf
  - Audit-Anforderungen
- **Folgeaufgabe:** Späteres Support-/Account-Recovery-Ticket.
- **ADR-relevant:** NEIN, nur als bewusst vertagte Einschränkung erwähnen

## 1.3 Einladungen ohne persönliche Firmenadresse

- **Status:** OUT_OF_SCOPE
- **Entscheidung / Antwort:** Im Pilot nicht vorgesehen.
- **Begründung:** Beschäftigte ohne individuelle erreichbare Einladungsadresse werden zunächst nicht unterstützt.
- **Spätere Optionen:**
  - Einladung an private Adresse
  - HR-Aktivierungscode
  - HR-System-Provisionierung
  - QR-Code mit zusätzlicher Identitätsprüfung
- **Folgeaufgabe:** Späteres Onboarding-/HR-Integrationskonzept.
- **ADR-relevant:** NEIN

---

# 2. Account, Membership und Arbeitgeberwechsel

## 2.1 Mehrere gleichzeitige Memberships

- **Status:** DECIDED
- **Entscheidung / Antwort:** Ein Nutzer darf niemals mehrere aktive Arbeitgeber-Memberships gleichzeitig besitzen.
- **Begründung:** Verhindert unklare Reporting-, Sponsoring- und Berechtigungskontexte.
- **Folgeaufgabe:** Datenbank-Constraint und Domain Rule definieren.
- **ADR-relevant:** JA

## 2.2 Beendigung und Wechsel einer Membership

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Eine bestehende Membership wird durch das Unternehmen aktiv beendet.
  - Der Nutzer kann seine Membership nicht selbst beenden.
  - Es gibt keine Nachlaufphase.
  - Alternativ kann die Annahme einer neuen Einladung eines anderen Arbeitgebers die bisherige Membership überschreiben beziehungsweise beenden.
  - Bei einer neuen Einladung meldet sich der Nutzer mit seinem bestehenden Account an und bestätigt die Übernahme.
  - Die primäre Account-E-Mail bleibt die vom Nutzer gewählte Adresse und wird durch den Arbeitgeberwechsel nicht ersetzt.
- **Begründung:** Das globale Nutzerkonto bleibt stabil, während die Arbeitgeberzugehörigkeit separat wechselt.
- **Noch zu konkretisieren:**
  - exakter Statuswechsel der alten Membership
  - atomare beziehungsweise idempotente Übernahme
  - Konfliktverhalten bei parallelen Einladungen
  - Audit des Wechsels
- **Folgeaufgabe:** Membership-Transition-Spezifikation.
- **ADR-relevant:** JA

## 2.3 Historisierung der Membership

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Memberships werden mit Zeitstempeln historisiert.
  - Reporting-Zuordnung erfolgt anteilig anhand der tatsächlichen Gültigkeitsdauer.
  - Rückwirkende Änderungen sind nicht vorgesehen.
- **Begründung:** Historische Aggregationen sollen den tatsächlich dokumentierten Zustand abbilden.
- **Noch zu konkretisieren:**
  - Zeitzone und Timestamp-Genauigkeit
  - Umgang mit Wechsel innerhalb eines Reporting-Zeitraums
  - genaue anteilige Berechnungsregel
- **Folgeaufgabe:** Reporting-Zeitlogik spezifizieren.
- **ADR-relevant:** JA

---

# 3. Globales Health Subject

## 3.1 Zeitpunkt und Ablauf der Erstellung

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Das globale Health Subject wird unmittelbar nach erfolgreicher Selbstregistrierung des Nutzers synchron provisioniert.
  - Die Identity-Registrierung wird vor der domänenübergreifenden Provisionierung committed. Schlägt diese anschließend fehl, bleibt die Registrierung gültig; ein generischer Logeintrag markiert den Reparaturbedarf und der idempotente Abgleich `elyo:provision-subjects` stellt die fehlende Zuordnung her.
  - Reihenfolge: zuerst der Health-Subject-Datensatz in `elyo_health`, danach das Mapping in `elyo_subject_mapping`. Ein verwaistes Subject ohne Mapping ist unkritisch und per Abgleich auffindbar.
  - Kein eigener persistierter Provisionierungsstatus; Idempotenz über deterministische Prüfung auf bereits existierendes Subject/Mapping zur `user_id` und wiederholbare Reparatur.
- **Begründung:** Nur tatsächlich registrierte Nutzer erhalten ein Health Subject. Der synchrone Ansatz ist die einfachste Lösung für den Pilot; da über DB-Grenzen keine echte Transaktion möglich ist, kompensieren Subject-zuerst-Reihenfolge und wiederholbarer Abgleich das Teilfehlerrisiko, ohne einen bereits angelegten Identity-Account zu verwerfen.
- **Folgeaufgabe:** Registrierungsablauf mit generischer Fehlerprotokollierung und idempotenten Abgleich für fehlende Zuordnungen implementieren.
- **ADR-relevant:** JA

## 3.2 Health Subject ohne aktive Membership

- **Status:** DECIDED
- **Entscheidung / Antwort:** Kostenloser Read-only-Basisstatus:
  - Account und Login bleiben ohne aktive Membership bestehen.
  - Bestehende Gesundheitsdaten und Dokumente bleiben sichtbar und exportierbar.
  - Keine neue Datenerfassung; Empfehlungen, Check-ins, Streaks und Badges sind pausiert.
  - Bei neuer Membership wird der volle Funktionsumfang reaktiviert.
- **Begründung:** Wahrt das Grundprinzip des arbeitgeberunabhängigen Gesundheitsverlaufs (0.3) bei minimalem laufendem Aufwand ohne Sponsor.
- **Folgeaufgabe:** Feature-Gating nach Membership-Status implementieren; Exportfunktion sicherstellen.
- **ADR-relevant:** JA, mindestens als Grundprinzip

## 3.3 Doppelte Accounts und mehrere Health Subjects

- **Status:** DECIDED
- **Fragen:**
  - Wie werden doppelte Accounts erkannt?
  - Können zwei Health Subjects zusammengeführt werden?
  - Wer darf eine Zusammenführung veranlassen?
  - Wie werden Daten-, Dokument- und Consent-Konflikte behandelt?
  - Ist eine fehlerhafte Zusammenführung reversibel?
- **Entscheidung / Antwort:**
  - Keine Zusammenführung, keine Account-Anlage im Pilot ohne Business Mail, diese ist mandatory
- **Begründung:**
  - Einfachheit
- **Folgeaufgabe:**
- **ADR-relevant:** Grundrichtung JA, Detailprozess später

---

# 4. Finanzierung und Zugangsrechte

## 4.1 Sponsor- und Entitlement-Modell

- **Status:** DECIDED
- **Fragen:**
  - Bezahlt ein Unternehmen pro eingeladenem, registriertem oder aktivem Nutzer?
  - Wann beginnt das Entitlement?
  - Wann endet es?
  - Gibt es Kulanz oder Abrechnungsperioden?
  - Können später Versicherer, öffentliche Programme oder Nutzer selbst Sponsor sein?
  - Können mehrere Entitlements nacheinander oder parallel existieren?
- **Entscheidung / Antwort:**
  - Pro Registeirung wird bezahlt, aktiv bis zum Ablauf des bezahltem Zeitaums 
- **Begründung:**
- **Folgeaufgabe:**
- **ADR-relevant:** Nur als Trennung von Account, Membership und Sponsoring

## 4.2 Leistungsumfang ohne aktiven Sponsor

- **Status:** DECIDED
- **Entscheidung / Antwort:** Ohne aktiven Sponsor gilt derselbe kostenlose Read-only-Basisstatus wie in 3.2:
  - Bestehende Daten und Dokumente bleiben sichtbar und exportierbar.
  - Keine neue Datenerfassung oder Änderung von Gesundheitsdaten.
  - Premium-Funktionen (Empfehlungen, Check-ins, Streaks, Badges) enden.
  - Keine Kulanzfrist; das Entitlement gilt bis zum Ablauf des bezahlten Zeitraums (4.1) und endet dann.
- **Begründung:** Ein einziges konsistentes Degradationsmodell für „ohne Membership“ und „ohne Sponsor“ ist einfach zu implementieren und zu kommunizieren.
- **Folgeaufgabe:** Feature-Gating an Entitlement-Status koppeln (gemeinsam mit 3.2 umsetzen).
- **ADR-relevant:** JA, weil es den Lifecycle des Health Subjects betrifft

---

# 5. Mapping-Domäne

## 5.1 Erlaubte Mapping-Operationen

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Operationen: `provisionOwnSubject`, `resolveOwnSubject`, `resolveReportingCohort`, `revokeSubjectLink`, `resolveForDataSubjectRequest`. Kein `mergeSubjects`, kein `restoreRevokedLink`.
  - `provisioningStateForUser` ist eine nicht-identifizierende Hilfsabfrage innerhalb der Provisionierungsoperation. Sie liefert ausschließlich `MISSING`, `ACTIVE` oder `REVOKED`, nutzt denselben Pflicht-Purpose und dasselbe Audit und darf nur in der Identity-Runtime verwendet werden.
  - Strikte Runtime-Operations-Matrix (jede Operation genau eine berechtigte Runtime):
    - Identity API: `provisionOwnSubject` (nur im Registrierungsablauf)
    - Employee Health API: `resolveOwnSubject` (nur eigene Session)
    - Reporting Worker: `resolveReportingCohort`
    - Privacy-/Admin-Workflow: `revokeSubjectLink`, `resolveForDataSubjectRequest`
    - Company API: keinerlei Mapping-Zugriff
  - Alle Operationen synchron; `resolveReportingCohort` läuft im Worker im Batch.
  - Jeder Aufruf trägt einen Pflicht-Purpose-Code (`REGISTRATION`, `SELF_ACCESS`, `REPORTING`, `DSR`, `REVOCATION`), der auditiert wird.
  - Rate Limits nur für `resolveForDataSubjectRequest`.
- **Begründung:** Minimales Operationsset mit klarer Einzelzuständigkeit pro Runtime setzt den Mapping-Grundschutz (0.4) technisch durch; synchrone Ausführung reicht für Pilot-Skala.
- **Folgeaufgabe:** Mapping-Contract ausformulieren (Signaturen, Fehlerfälle, Audit-Felder).
- **ADR-relevant:** JA

## 5.2 Mapping-Statusmodell

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Nur zwei Statuswerte: `ACTIVE` und `REVOKED`.
  - Kein `PENDING` (fehlende Zuordnungen werden als nicht persistierter Zustand `MISSING` erkannt und synchron repariert, siehe 3.1), kein `MERGED` (keine Zusammenführung, siehe 3.3), kein `LOCKED`.
  - `REVOKED` ist endgültig; keine Reaktivierung (siehe 5.1: kein `restoreRevokedLink`).
  - Kein physisches Löschen im Normalbetrieb; die widerrufene Zeile bleibt als Tombstone erhalten und verhindert Wiederverwendung der IDs. Physische Löschung nur im Rahmen des Löschverfahrens (11.4).
- **Begründung:** Minimales Zustandsmodell ohne Zustände, die im Pilot nie eintreten; weniger Test- und Fehlerfläche.
- **Folgeaufgabe:** Statusfeld und Übergangsregeln im Mapping-Contract festschreiben.
- **ADR-relevant:** JA

## 5.3 Rückwärtsauflösung / Re-Identifizierung

- **Status:** DECIDED
- **Entscheidung / Antwort:** Keine Rückwärtsauflösung im normalen Produktcode. Nur über einen auditierten Privacy-/Security-Workflow mit strengen Leitplanken:
  - Erlaubte Zwecke ausschließlich: DSGVO-Betroffenenanfrage, Sicherheitsvorfall, gesetzliche Pflicht.
  - Vier-Augen-Prinzip: vorherige Freigabe durch eine zweite Person zwingend.
  - Nur Einzelauflösung, keine Listenauflösung.
  - Zeitlich begrenzter Zugriff (Richtwert 24 Stunden).
  - Exportverbot für aufgelöste Zuordnungen.
  - Automatische Benachrichtigung der zweiten Kontrollperson bei jeder Auflösung.
- **Begründung:** Re-Identifizierung soll ein seltener, kontrollierter Ausnahmeprozess sein; präventive Freigabe schützt stärker als nachgelagerte Kontrolle.
- **Folgeaufgabe:** Break-glass- und Privacy-Workflow inkl. Freigabetool spezifizieren; zweite Kontrollperson benennen (siehe 9.3).
- **ADR-relevant:** JA

---

# 6. Runtime- und Deployment-Architektur

## 6.1 Exakte Runtime-Aufteilung

- **Status:** DECIDED
- **Entscheidung / Antwort:** Fünf Runtimes aus gemeinsamem Codebestand:
  1. Identity API
  2. Employee Health API
  3. Company API
  4. Reporting Worker (geplant per Scheduler/Cron, nicht dauerhaft)
  5. Privacy/Admin-Runtime (`revokeSubjectLink`, DSR-Auflösung, Break-glass)
  - Mapping erhält keinen eigenen Prozess, sondern bleibt Modul innerhalb der jeweils berechtigten Runtime gemäß Matrix aus 5.1; Absicherung über getrennte DB-Credentials (6.2).
  - Deployment: ein identisches Container-Image; das Startprofil (ENV) bestimmt geladene Routen/Module und eingebundene Secrets.
- **Begründung:** Separate Privacy/Admin-Runtime trennt Break-glass technisch vom Normalbetrieb; ein Image mit Startprofilen hält Build und Release einfach.
- **Folgeaufgabe:** Startprofile und Modul-Lademechanismus definieren; Scheduler für Reporting Worker festlegen.
- **ADR-relevant:** JA

## 6.2 Credential-Isolation

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Je Runtime eine eigene PostgreSQL-Rolle mit minimalen Rechten nur auf die benötigten Datenbanken (Least Privilege gemäß Matrix aus 5.1/6.1). Company API erhält keinerlei Zugriff auf `elyo_subject_mapping` und `elyo_health`.
  - Separate Migration-Rolle mit DDL-Rechten, die ausschließlich in der Deploy-Pipeline verwendet wird und nie in Runtime-Containern vorhanden ist.
  - Secrets werden als Docker Secrets je Startprofil eingebunden; nie im Image oder Repository.
  - Rotation im Pilot manuell nach dokumentiertem Runbook (halbjährlich sowie bei Personalwechsel oder Verdacht).
  - Lokal/Test/Staging: eigene Wegwerf-Credentials, niemals Produktionswerte.
- **Begründung:** Rollentrennung pro Runtime setzt die Credential-Isolation aus 0.2 auf DB-Ebene durch; Docker Secrets plus manuelles Runbook sind für Pilot-Skala angemessen, ein Secret Manager kann später nachgerüstet werden.
- **Folgeaufgabe:** Access Matrix (Rolle × Datenbank × Rechte) erstellen; Rotation-Runbook schreiben.
- **ADR-relevant:** JA

## 6.3 Kommunikation zwischen Runtimes

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Keine direkte Runtime-zu-Runtime-Kommunikation im Pilot: kein internes HTTP, keine Queue.
  - Jede Runtime liest/schreibt ausschließlich ihre erlaubten Datenbanken (gemäß 6.2).
  - Employee-Zugriffe sind synchrone DB-Reads innerhalb der eigenen Runtime.
  - Reporting arbeitet asynchron im Pull-Modell: Der geplante Reporting Worker (6.1) holt sich seine Arbeit selbst.
  - Jeder Request und jeder Job erhält eine Correlation ID, die in allen Logs mitgeführt wird.
  - Ausfallverhalten: Fällt eine Runtime aus, sind andere nicht betroffen; fehlgeschlagene Worker-Läufe werden beim nächsten geplanten Lauf nachgeholt.
- **Begründung:** Minimale Betriebskomplexität für den Pilot; Timeouts/Retries zwischen Services entfallen, weil es keine synchronen Abhängigkeiten gibt. Queue/Outbox kann später ergänzt werden.
- **Folgeaufgabe:** Correlation-ID-Konvention und Logging-Standard festlegen.
- **ADR-relevant:** JA

---

# 7. Reporting und Arbeitgeberzugriff

## 7.1 Zeitliche Grenze der Datennutzung

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - In Arbeitgeber-Reports fließen ausschließlich Daten ein, die während der aktiven Membership beim jeweiligen Arbeitgeber entstanden sind.
  - Vorher privat erfasste Daten und Daten aus früheren Arbeitgeberkontexten sind ausgeschlossen.
  - Maßgeblich ist das Messdatum (fachlicher Entstehungszeitpunkt).
  - Nachträge werden berücksichtigt, solange das Messdatum innerhalb der Membership liegt und der betreffende Reporting-Zeitraum noch nicht freigegeben ist; danach werden sie für Reports ignoriert (kein Neuberechnen freigegebener Reports, siehe 7.2).
- **Begründung:** Der Arbeitgeber sieht nur Aggregate über Daten aus seinem eigenen Sponsoring-Kontext; das Messdatum ist die fachlich korrekte Zuordnung, das Freigabefenster schützt die Snapshot-Stabilität.
- **Folgeaufgabe:** Scope-Filter im Reporting Worker implementieren; Freigabezeitpunkte je Reporting-Zeitraum definieren.
- **ADR-relevant:** JA

## 7.2 Historische Reporting-Snapshots

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Historische Aggregationen bleiben erhalten. Nach Ende einer Membership fließt das Subject nicht mehr in neue Arbeitgeberberichte ein.
  - Freigegebene Snapshots sind unveränderlich (immutable).
  - Korrekturen erfolgen ausschließlich als neue Snapshot-Version mit Verweis auf die vorherige.
  - Keine Neuberechnung historischer Zeiträume im Normalbetrieb.
  - Nachträgliche Löschung oder Widerruf wirkt nur auf zukünftige Reports; bestehende Aggregate bleiben, da sie suppressionsgeprüft und ohne Personenbezug sind (0.5).
  - Jeder Snapshot speichert seine Berechnungsversion (Algorithmus, Schwellenwerte, Softwarestand).
- **Begründung:** Berichte müssen dauerhaft referenzierbar und nachvollziehbar sein; anonyme Aggregate unterliegen nicht der Löschpflicht.
- **Folgeaufgabe:** Snapshot-Versionierungsschema und Metadatenfelder definieren.
- **ADR-relevant:** JA

## 7.3 Kohortenbildung

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Im Pilot existiert genau eine Kohorte je Kunde: alle aktiven Members im Reporting-Zeitraum (Gesamtunternehmen).
  - Keine Team-, Standort- oder Abteilungslogik, keine freien Filter, keine überlappenden Einheiten.
  - Die aufgelöste Subject-Liste existiert ausschließlich im Arbeitsspeicher des Reporting Workers während des Laufs und wird niemals persistiert; gespeichert werden nur suppressionsgeprüfte Aggregate (0.5).
- **Begründung:** Eine einzige Gesamtkohorte minimiert die Re-Identifikationsfläche und vereinfacht Suppression (8.x) erheblich; feinere Kohorten können später als eigene Erweiterung mit Privacy Review folgen.
- **Folgeaufgabe:** Späteres Ticket für vordefinierte Kohorten (Standort/Team) inkl. Differenzangriffs-Analyse.
- **ADR-relevant:** JA

## 7.4 Reporting Tenant Identifier

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Reporting kennt die echte `company_id` nicht; es wird eine eigene `reporting_tenant_id` verwendet.
  - Das Mapping Company ↔ Reporting Tenant liegt in der Mapping-Domäne (`elyo_subject_mapping`).
  - Eigene Team-/Kohortenkennungen braucht Reporting im Pilot nicht (nur Gesamtkohorte, siehe 7.3).
- **Begründung:** Konsistent zum Grundprinzip, dass `elyo_reporting` keine Fremd-Identifikatoren enthält; ein Leak der Reporting-DB verrät keine Kundenzuordnung.
- **Folgeaufgabe:** Tenant-Mapping-Tabelle und Auflösungsoperation im Mapping-Contract ergänzen.
- **ADR-relevant:** JA

---

# 8. Suppression und Re-Identifikationsschutz

## 8.1 Mindestschwelle

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Plattform-Mindestschwelle: `10` als Standard.
  - Für als sensibel klassifizierte Kennzahlen (z. B. Wellbeing-Scores) gilt pauschal `metric_threshold = 20`.
  - Der Kunde darf die Plattformschwelle nicht unterschreiten, aber eine höhere eigene Schwelle wählen.
  - Formel: `effective_threshold = max(platform_minimum, customer_threshold, metric_threshold)`
- **Begründung:** 10 als Basis bleibt bei kleinen Pilotkunden praktikabel; die pauschale 20 für sensible Kennzahlen schützt die kritischsten Inhalte ohne komplexes Schwellenmodell.
- **Folgeaufgabe:** Sensibilitätsklassifikation aller Reporting-Kennzahlen erstellen (Voraussetzung für metric_threshold; verknüpft mit 8.5 und 14.1).
- **ADR-relevant:** JA

## 8.2 Definition der Zähler

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - `eligible_count`: Anzahl aktiver Members im Reporting-Zeitraum (aus historisierten Memberships, 2.3).
  - `contributor_count`: Anzahl Subjects mit mindestens einem Datenpunkt zur jeweiligen Kennzahl im Zeitraum.
  - Freigabebedingung: `eligible_count >= effective_threshold` UND `contributor_count >= effective_threshold`.
  - `response_count >= effective_threshold` gilt zusätzlich nur bei Survey-basierten Kennzahlen.
- **Begründung:** Die Kombination verhindert sowohl Rückschlüsse aus kleinen Belegschaften als auch aus wenigen Beitragenden; response_count ist nur dort sinnvoll, wo Antworten existieren.
- **Folgeaufgabe:** Zählerberechnung im Reporting Worker implementieren und je Kennzahl dokumentieren.
- **ADR-relevant:** JA

## 8.3 Kleine Kategorien und Verteilungen

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Kategorien mit weniger als 5 Beitragenden werden zu „Sonstige“ zusammengefasst.
  - Ist auch „Sonstige“ kleiner als 5 oder bleiben weniger als 2 Kategorien übrig, wird die gesamte Verteilung unterdrückt.
  - Ja/Nein-Fragen: Beide Ausprägungen müssen mindestens 5 Beitragende haben, sonst wird die Frage komplett unterdrückt.
  - Prozentwerte werden auf ganze 5 % gerundet.
- **Begründung:** Kategorie-Mindestgröße 5 plus Zusammenfassung erhält Aussagekraft bei Pilotgrößen und verhindert 1-Personen-Kategorien; grobe Rundung erschwert Rückrechnung.
- **Folgeaufgabe:** Suppression-Regeln als wiederverwendbare Bibliothek im Reporting Worker implementieren und testen (19.1).
- **ADR-relevant:** JA

## 8.4 Differenzangriffe

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Reports werden ausschließlich für feste Kalenderquartale erzeugt, einmalig als unveränderlicher Snapshot (7.2).
  - Keine freien Zeiträume, keine On-Demand-Neuberechnung, keine Filter (nur Gesamtkohorte, 7.3) – Differenzangriffe sind damit strukturell weitgehend ausgeschlossen.
  - Komplementäre Suppression ist im Pilot nicht erforderlich, da es keine Teilkohorten gibt.
  - Alle Report-Erzeugungen werden protokolliert (10.1).
- **Begründung:** Feste Quartale mit Einmal-Snapshots eliminieren die Angriffsfläche durch wiederholte, leicht veränderte Abfragen, statt sie nachträglich zu erkennen.
- **Folgeaufgabe:** Bei Einführung feinerer Kohorten oder Frequenzen (Folgeticket zu 7.3) Differenzangriffs-Analyse und komplementäre Suppression nachziehen.
- **ADR-relevant:** JA

## 8.5 Grundsätzlich nicht reportbare Kennzahlen

- **Status:** DECIDED
- **Entscheidung / Antwort:** Grundsätzlich nicht reportbar – auch nicht aggregiert – sind:
  - individuelle Laborwerte
  - Diagnosen
  - Medikamenteninformationen
  - seltene Erkrankungen
  - Freitexte
  - Dokumentinhalte
  - individuelle Empfehlungen
  - psychische Gesundheitsangaben
  - Schwangerschaft und vergleichbar sensible Merkmale
  - Reportbar sind nur abstrahierte Scores (z. B. Wellbeing-Index, mit metric_threshold 20 gemäß 8.1), Teilnahmequoten und Aktivitätskennzahlen.
  - Es gilt das Allowlist-Prinzip: Jede neue Reporting-Kennzahl benötigt eine explizite Freigabe (Änderungsprozess 17.2), statt einer Blocklist-Prüfung im Nachhinein.
- **Begründung:** Medizinisch konkrete Inhalte haben in Arbeitgeber-Reports nichts verloren; das Allowlist-Prinzip verhindert schleichende Erweiterung.
- **Folgeaufgabe:** Initiale Allowlist der Pilot-Kennzahlen erstellen und mit Privacy-Abnahme (18.1) abstimmen.
- **ADR-relevant:** JA

---

# 9. Admin-, Support- und Break-glass-Modell

## 9.1 Rollenmodell

- **Status:** DECIDED
- **Entscheidung / Antwort:** Vier Kernrollen im Pilot:
  - **Platform Admin:** Betrieb und Konfiguration; kein Zugriff auf Health-Daten oder Mapping.
  - **Company Admin:** Kundenseite – Einladungen verwalten, freigegebene Reports lesen; nur Company-Domäne.
  - **Support Admin:** E-Mail-Änderung auf bestätigte Anfrage (1.2); kein Health- oder Mapping-Zugriff.
  - **Privacy Admin:** Mapping-Auflösung (`revokeSubjectLink`, DSR), Audit-Lesen; agiert ausschließlich über die Privacy/Admin-Runtime (6.1).
  - Security-, Infrastructure-, Medical-Admin und Reporting Operator entfallen im Pilot bzw. gehen im Platform Admin auf.
- **Begründung:** Minimales Rollenset, das die kritische Trennung (Mapping-Auflösung nur Privacy Admin) erhält, ohne Papierrollen zu erzeugen, die ohnehin dieselbe Person besetzt.
- **Folgeaufgabe:** Rollen-Rechte-Matrix (Rolle × Domäne × Operation) erstellen.
- **ADR-relevant:** JA

## 9.2 Pilotzugriff des Tech-Leads

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Der Tech-Lead nutzt im Pilot einen privilegierten Admin-Account (kein getrennter Account je Rolle), abgesichert mit MFA.
  - Alle administrativen Aktionen werden vollständig auditiert; die Angabe eines Purpose-Codes ist bei allgemeinen Admin-Aktionen freiwillig.
  - Ausnahme: Für Re-Identifizierung/Break-glass gelten unverändert die strengeren Regeln aus 5.3 (Pflicht-Purpose, Vier-Augen-Freigabe, zeitliche Begrenzung, Benachrichtigung der zweiten Person).
  - Direkte SQL-Zugriffe auf Produktionsdatenbanken sind kein Alltagswerkzeug und laufen nur über den Break-glass-Prozess.
- **Begründung:** Ein Account mit MFA und lückenlosem Audit hält die Reibung im Pilot gering; die kritischen Zugriffe bleiben durch 5.3 geschützt.
- **Folgeaufgabe:** Nach dem Pilot prüfen, ob getrennte Rollen-Accounts eingeführt werden.
- **ADR-relevant:** JA

## 9.3 Zweite Kontrollperson und unabhängige Privacy-Abnahme

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Eine benannte interne zweite Person übernimmt die Kontrollfunktion: Vier-Augen-Freigabe der Break-glass-/Re-Identifizierungszugriffe (5.3) und Prüfung der zugehörigen Audit-Einträge.
  - Die Privacy-Abnahme der ADR erfolgt ebenfalls intern (Details in 18.1).
  - Externe Datenschutzberatung ist vor dem ersten Pilot nicht zwingend; sie kann später hinzugezogen werden.
- **Begründung:** Pragmatische Pilotlösung ohne externe Abhängigkeiten; die interne Person muss ausreichend technisches Verständnis für die Freigabeprüfung besitzen.
- **Folgeaufgabe:** Kontrollperson namentlich benennen und in den Break-glass-Workflow (5.3) einbinden.
- **ADR-relevant:** JA

---

# 10. Auditierung

## 10.1 Umfang der Auditierung

- **Status:** DECIDED
- **Entscheidung / Antwort:** Auditiert wird das sicherheitskritische Set:
  - alle Mapping-Zugriffe
  - Break-glass
  - Berechtigungsänderungen
  - Löschung und Restore
  - Exporte
  - Dokumentabrufe
  - Reporting-Jobs
  - fehlgeschlagene Zugriffe
  - Normale Health-Lese-/Schreibzugriffe des Nutzers auf eigene Daten werden nicht einzeln auditiert (nur normales App-Logging).
- **Begründung:** Fokus auf Vorgänge mit Missbrauchspotenzial; ein Voll-Audit aller Selbstzugriffe erzeugte ein sensibles Verhaltensprofil und hohes Datenvolumen ohne Sicherheitsgewinn.
- **Folgeaufgabe:** Audit-Eventkatalog mit Event-Typen und Pflichtfeldern erstellen.
- **ADR-relevant:** JA

## 10.2 Inhalt der Auditdaten

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Felder je Audit-Eintrag: Service Identity, menschlicher Actor, Purpose Code, Operation, Zielreferenz, Zeitstempel, Ergebnis, Correlation ID, Freigabereferenz, Quell-Runtime.
  - Zielreferenzen sind ausschließlich domänen-lokal: Identity-Events referenzieren `user_id`, Health-Events `health_subject_id` – niemals beide in einem Eintrag.
  - Keine Tokenisierung im Pilot; der Schutz entsteht durch die strikte Trennung der Referenzen.
- **Begründung:** Ohne gemeinsames Auftreten beider IDs kann das Audit nie als Ersatz-Mapping missbraucht werden; Tokenisierung wäre zusätzliche Indirektion ohne proportionalen Gewinn.
- **Folgeaufgabe:** Schema-Validierung/Lint-Regel, die gemischte Referenzen in Audit-Events technisch verhindert.
- **ADR-relevant:** JA

## 10.3 Schutz und Aufbewahrung des Audits

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Eigene Audit-Datenbank auf dem Cluster; Runtimes erhalten ausschließlich INSERT-Rechte (kein UPDATE/DELETE per Grant) – append-only auf DB-Rollen-Ebene.
  - Leserecht nur für den Privacy Admin (über die Privacy/Admin-Runtime).
  - Aufbewahrungsdauer: 2 Jahre.
  - Alarme im Pilot nur für Break-glass-Events (automatische Benachrichtigung der zweiten Kontrollperson, 5.3/9.3).
  - Manipulationserkennung per Hash-Verkettung: optional, später nachrüstbar.
  - Restore-Verhalten wird gemeinsam mit 16.2 geregelt (Audit-Log gilt dabei als führend für Nachvollziehbarkeit).
- **Begründung:** DB-Rollen-basiertes Append-only ist mit Bordmitteln umsetzbar und für Pilot-Skala ausreichend; ein externes Logsystem kann später ergänzt werden.
- **Folgeaufgabe:** Audit-DB inkl. Grants in der Access Matrix (6.2) ergänzen; Break-glass-Alarmweg einrichten.
- **ADR-relevant:** JA

---

# 11. Löschung, Widerruf und Deaktivierung

## 11.1 Account-Deaktivierung

- **Status:** DECIDED
- **Entscheidung / Antwort:** Zwei-Stufen-Modell – ein Account ist entweder aktiv oder gelöscht:
  - Kein separater Sperr- oder Deaktivierungszustand im Pilot.
  - Löschung folgt dem Löschverfahren nach 11.4; mit Einleitung der Löschung werden alle Sessions widerrufen.
  - Membership, Mapping und Health Subject bleiben bis zur Löschung unverändert bestehen.
  - Keine Reaktivierung nach Löschung; bei Bedarf neue Registrierung (neues Health Subject, keine Zusammenführung gemäß 3.3).
- **Begründung:** Einfachstes Zustandsmodell für den Pilot; Missbrauchsfälle sind bei kleiner Pilotpopulation über den Support handhabbar. Bewusst akzeptiert: kein reversibler Sperrzustand.
- **Folgeaufgabe:** Nach dem Pilot prüfen, ob ein SUSPENDED-Zustand (Missbrauch, Nutzerwunsch) nachgerüstet wird.
- **ADR-relevant:** JA

## 11.2 Ende einer Membership

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Keine Nachlaufphase; das Unternehmen beendet die Membership aktiv; eine neue Einladung kann den Wechsel auslösen; keine Teilnahme an neuen Arbeitgeberreports nach Membership-Ende (alles bereits festgelegt).
  - Laufende arbeitgebergesponserte Maßnahmen enden sofort mit der Membership.
  - Das Entitlement endet zum Ende des bezahlten Zeitraums (4.1); danach gilt der Read-only-Basisstatus (3.2/4.2).
  - Bereits gestartete Reporting-Jobs laufen zu Ende; das Subject zählt im Quartals-Snapshot anteilig gemäß Historisierung (2.3), in späteren Snapshots gar nicht mehr.
- **Begründung:** Harter Schnitt hält die Regeln einfach und konsistent mit 2.2/2.3; die anteilige Zählung bildet den dokumentierten Zustand korrekt ab.
- **Folgeaufgabe:** Membership-Ende-Ablauf in der Membership-Transition-Spezifikation (2.2) mitbehandeln.
- **ADR-relevant:** JA

## 11.3 Einwilligungswiderruf

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Einwilligungen sind zweckgebunden (z. B. Reporting-Teilnahme, optionale Datenarten) und einzeln widerrufbar.
  - Widerruf stoppt die zukünftige Verarbeitung für den jeweiligen Zweck; Bestandsdaten bleiben erhalten.
  - Freigegebene Aggregate bleiben bestehen (7.2).
  - Das Mapping bleibt aktiv – Widerruf ist keine Löschung; dafür existiert 11.4.
  - Widerruf der Reporting-Einwilligung nimmt das Subject ab sofort aus neuen Kohorten.
- **Begründung:** Widerruf muss so granular sein wie die Einwilligung; die Trennung von Widerruf und Löschung hält beide Rechte sauber auseinander.
- **Folgeaufgabe:** Einwilligungszwecke katalogisieren (mit 14.3 Consent Records abstimmen).
- **ADR-relevant:** Grundprinzip JA

## 11.4 Löschanfrage

- **Status:** DECIDED
- **Entscheidung / Antwort:** Löschverfahren mit 30-Tage-Frist:
  - Identity- und Health-Daten werden physisch gelöscht; die Mapping-Zeile zuletzt (bleibt bis Abschluss als Tombstone, 5.2).
  - Freigegebene Aggregate bleiben bestehen – sie sind suppressionsgeprüft und anonym (7.2).
  - Object Storage: Objekte werden gelöscht; Caches werden invalidiert.
  - Logs: laufen über ihre Retention aus (kein aktives Durchsuchen).
  - Backups: Löschung greift über den Rotationszyklus mit dokumentierter Frist (90 Tage); der Restore-Prozess prüft die Löschliste, damit gelöschte Daten nicht wiederbelebt werden (16.2).
  - Der Audit-Eintrag der Löschung selbst bleibt erhalten (10.1).
- **Begründung:** Physische Löschung mit realistischen Backup-Fristen ist ehrlicher als nicht belastbare Sofort-Anonymisierung; die Löschlisten-Prüfung schließt die Restore-Lücke.
- **Folgeaufgabe:** Löschworkflow inkl. Löschliste und Fristen-Monitoring implementieren.
- **ADR-relevant:** JA

## 11.5 Kündigung eines Pilotkunden

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Der Company-Zugriff endet zum Vertragsende.
  - Alle Memberships des Kunden werden beendet; die Nutzer fallen in den Read-only-Basisstatus (3.2), ihre persönlichen Health Accounts und Health Subjects bleiben bestehen.
  - Freigegebene Reports bleiben dem Kunden als Export erhalten.
  - Kundenspezifische Aggregate werden nach einer vertraglichen Frist (12 Monate) gelöscht.
- **Begründung:** Konsistent mit dem arbeitgeberunabhängigen Health Subject (0.3); der Kunde behält seine bezahlten Berichte, ohne dass dauerhaft kundenbezogene Daten vorgehalten werden.
- **Folgeaufgabe:** Offboarding-Ablauf für Kunden definieren (Export, Fristen, Löschbestätigung).
- **ADR-relevant:** JA

---

# 12. Verschlüsselung und Schlüsselverwaltung

## 12.1 Datenverschlüsselung

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Volume-/Disk-Verschlüsselung für den gesamten Cluster; Backups verschlüsselt.
  - Zusätzlich anwendungsseitige Feldverschlüsselung ausschließlich für die Mapping-Tabelle (`user_id` ↔ `health_subject_id`) mit eigenem Schlüssel – ein Dump der Mapping-DB ist allein wertlos.
  - Health-Daten ohne zusätzliche Feldverschlüsselung; Schutz über DB-Trennung (0.1) und Rollen (6.2).
  - Temporäre Kohorten benötigen keine Verschlüsselung, da sie nur im Arbeitsspeicher existieren (7.3).
- **Begründung:** Der Aufwand konzentriert sich auf das Kronjuwel (Mapping); flächige Feldverschlüsselung würde Suche/Indizes verkomplizieren ohne verhältnismäßigen Gewinn.
- **Folgeaufgabe:** Feldverschlüsselung im Mapping-Contract implementieren; Backup-Verschlüsselung in 16.1 mitregeln.
- **ADR-relevant:** JA, mindestens als Mindeststandard

## 12.2 Schlüsselmanagement

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Schlüssel werden als Docker Secrets verwaltet (konsistent zu 6.2), getrennt pro Domäne.
  - Der Mapping-Feldschlüssel liegt nur in Runtimes mit Mapping-Berechtigung (Matrix 5.1).
  - Rotation manuell nach Runbook (gemeinsam mit Credential-Rotation, 6.2).
  - Alte Backup-Schlüssel werden bis zum Ablauf der Backup-Retention aufbewahrt, damit Restores nach Rotation möglich bleiben.
  - Keine separate Auditierung der Schlüsselnutzung im Pilot.
- **Begründung:** Ein zentrales KMS wäre für den Pilot Überbau; getrennte Schlüssel pro Domäne erhalten die Trennungsidee mit Bordmitteln.
- **Folgeaufgabe:** Rotation-Runbook (6.2) um Schlüsselrotation und Backup-Schlüssel-Aufbewahrung ergänzen; KMS-Migration nach dem Pilot prüfen.
- **ADR-relevant:** JA

---

# 13. Dokumente und Object Storage

## 13.1 Speicherort und Zugriff

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Eigener Bucket für Health-Dokumente mit eigenen Credentials; Zugriff ausschließlich durch die Employee Health API.
  - Getrennte Buckets je Umgebung (lokal/Test/Staging/Produktion).
  - Auslieferung über kurzlebige signierte Download-URLs (Gültigkeit 5 Minuten).
  - Serverseitige Objektverschlüsselung aktiviert.
- **Begründung:** Setzt die Credential-Trennung (6.2) auf Storage-Ebene fort; Signed URLs vermeiden API-Streaming-Last bei kontrollierter Zugriffsdauer.
- **Folgeaufgabe:** Bucket-Provisionierung und Credential-Vergabe in der Access Matrix ergänzen.
- **ADR-relevant:** JA

## 13.2 Dateinamen und Metadaten

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Storage-Schlüssel sind zufällige UUIDs unter einem `health_subject_id`-Prefix; niemals Name, E-Mail oder `user_id` im Pfad.
  - Der Originaldateiname wird ausschließlich als Anzeige-Attribut in `elyo_health` gespeichert, nicht im Storage.
  - PDF-/EXIF-Metadaten werden beim Upload bereinigt.
- **Begründung:** Der Storage-Layer bleibt frei von Identitätsbezug; Nutzer behalten trotzdem wiedererkennbare Dateinamen in der Oberfläche.
- **Folgeaufgabe:** Metadaten-Bereinigung in die Upload-Pipeline (13.3) integrieren.
- **ADR-relevant:** JA

## 13.3 Verarbeitung von Dokumenten

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Upload-Pipeline: Virenscan (z. B. ClamAV), Dateityp-Allowlist (PDF, JPG, PNG), Größenlimit 25 MB, Metadaten-Bereinigung (13.2).
  - Kein OCR und keine strukturierte Extraktion im Pilot; das Originaldokument ist die einzige Quelle.
  - Download und Vorschau werden auditiert (10.1).
- **Begründung:** Grundschutz gegen Malware und Fehluploads ohne dem neuen Laborwertmodell (0.6) vorzugreifen.
- **Folgeaufgabe:** OCR/Extraktion als spätere Erweiterung zusammen mit dem Laborwert-Domänenmodell bewerten.
- **ADR-relevant:** Grundprinzip JA, Details später

---

# 14. Datenmodellgrenzen

## 14.1 Klassifikation gesundheitsnaher Daten

- **Status:** DECIDED
- **Für jedes Objekt ist die Zieldomäne festzulegen:**
  - Screeningantworten
  - Check-ins
  - Wellbeing
  - Laborwerte
  - Dokumente
  - Wearables
  - Empfehlungen
  - Maßnahmenzuweisungen
  - Maßnahmenbeteiligung
  - Verifikationen
  - Punkte
  - Streaks
  - Badges
  - Survey-Antworten
  - Freitexte
  - Einwilligungen
- **Mögliche Zieldomänen:**
  - Identity
  - Mapping
  - Health
  - Reporting
  - Access/Entitlement
  - minimiertes Read Model
- **Entscheidung / Antwort:**
  - Alle fachlichen bzw. verhaltensbeschreibenden Objekte liegen in der Health-Domäne (Bezug: `health_subject_id`): Screeningantworten, Check-ins, Wellbeing, Laborwerte, Dokumente, Wearables, Empfehlungen, Maßnahmenzuweisungen, Maßnahmenbeteiligung, Verifikationen, Punkte, Streaks, Badges, Survey-Antworten, Freitexte.
  - Einwilligungen → Identity-Domäne gemäß 14.3.
  - Reporting erhält ausschließlich suppressionsgeprüfte Aggregate (0.5).
  - Kein minimiertes Read Model im Pilot; die UI liest Gamification-Daten über die Employee Health API.
- **Begründung:** Eine einzige klare Regel („alles, was Gesundheit oder Verhalten beschreibt, liegt bei Health“) verhindert Grenzfalldiskussionen; auch Streaks/Badges verraten Gesundheitsverhalten und gehören daher nicht zu Identity.
- **Folgeaufgabe:** Datenklassifikationsmatrix als Tabelle ausarbeiten (verknüpft mit 15.1/15.2).
- **ADR-relevant:** JA

## 14.2 Unternehmenskontext in der Health-Domäne

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Die Health-Domäne kennt keine `company_id` und keine vergleichbare Referenz.
  - Arbeitgebergesponserte Maßnahmen werden in der Identity-Domäne über Membership + Entitlement modelliert; Health kennt nur die Maßnahme selbst und die Teilnahme des Subjects.
  - Entitlements liegen in der Identity-Domäne; keine eigene Access-Datenbank im Pilot.
- **Begründung:** Ein Health-Dump darf keine Arbeitgeberzugehörigkeit verraten (Trennungsprinzip 0.1/0.5); Sponsoring ist ein Zugangs-, kein Gesundheitsthema.
- **Folgeaufgabe:** Maßnahmen-/Entitlement-Modell in Identity ausarbeiten.
- **ADR-relevant:** JA

## 14.3 Consent Records

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Consent Records liegen in der Identity-Domäne, bezogen auf `user_id`.
  - Jeder Record referenziert eine versionierte Einwilligungstext-Version und genau einen Zweck (Zweckbindung, konsistent zu 11.3).
  - Widerruf erfolgt pro Zweck als neuer Record (append-only, keine Überschreibung).
  - Health prüft Einwilligungen über die eigene Runtime, ohne Consent-Texte zu duplizieren.
- **Begründung:** Einwilligungen gehören rechtlich zur natürlichen Person (User), nicht zum Pseudonym; append-only macht die Einwilligungshistorie nachweisbar.
- **Folgeaufgabe:** Consent-Datenmodell und Zweckkatalog (11.3) implementieren.
- **ADR-relevant:** JA

---

# 15. Bestands- und Demo-Risikoinventar

## 15.1 Vollständige Inventarisierung

- **Status:** DECIDED
- **Mindestens zu prüfen:**
  - `lab_markers`
  - `wellbeing_entries`
  - `user_documents`
  - `health_documents`
  - `anamnesis`
  - `survey_responses`
  - `survey_answers`
  - Wearable-Tabellen
  - Check-in-Daten
  - Measure Participation
  - Verification
  - Recommendations
  - Points/Streaks/Badges
  - alle direkten `user_id`-Verknüpfungen
- **Entscheidung / Antwort:**
  - Vollinventar vor Beginn der Migration: Alle genannten Strukturen werden systematisch erfasst (Schema, Datenvolumen, `user_id`-Bezüge, Zieldomäne nach 14.1).
  - Ergebnis ist eine Inventartabelle als eigenes Arbeitsdokument; die Migration startet erst nach Abschluss des Inventars.
- **Begründung:** Querbezüge über `user_id`-Joins müssen vollständig bekannt sein, bevor Grenzen gezogen werden; übersehene Nebentabellen sind das typische Leak-Risiko.
- **Folgeaufgabe:** Code- und Schema-Inventar als eigenes Ticket durchführen.
- **ADR-relevant:** JA

## 15.2 Klassifikation der Bestandsstrukturen

- **Status:** DECIDED
- **Für jede Struktur festlegen:**
  - verwerfen
  - fachlich neu entwerfen
  - transformiert migrieren
  - nur Demo-Daten löschen
  - teilweise übernehmen
- **Zusätzlich:**
  - Zielmodell
  - Datenqualität
  - Rechtsgrundlage
  - Validierung
- **Entscheidung / Antwort:**
  - Standardklassifikation ist „verwerfen / fachlich neu entwerfen“.
  - Eine Struktur wird nur übernommen oder transformiert migriert, wenn ein expliziter, dokumentierter Grund vorliegt; je Struktur werden Zielmodell, Datenqualität, Rechtsgrundlage und Validierung festgehalten.
- **Begründung:** Konsistent mit 0.6 – Demo-Strukturen sind kein Zielmodell; die Beweislast liegt bei der Übernahme, nicht beim Verwerfen.
- **Folgeaufgabe:** Klassifikation je Struktur in der Inventartabelle (15.1) dokumentieren.
- **ADR-relevant:** JA

## 15.3 Demo-Laborwerte

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Das Demo-Schema wird nicht fortgeführt (bereits festgelegt, 0.6).
  - Alle bestehenden Demo-Laborwerte werden vollständig gelöscht; es gibt keine migrationswürdigen Pilotdaten.
  - Demo-Grenzwerte und zugehörige UI-Logik werden explizit nicht übernommen; Code-Reviews prüfen gezielt auf versehentliche Übernahmen.
- **Begründung:** Das neue Laborwertmodell (0.6) startet ohne fachliche und regulatorische Altlasten.
- **Folgeaufgabe:** Produktives Laborwertmodell (eigenes Ticket); Löschskript für Demo-Laborwerte.
- **ADR-relevant:** JA

---

# 16. Backup und Restore

## 16.1 Backup-Trennung

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Täglicher logischer Dump je Datenbank (`elyo_identity`, `elyo_subject_mapping`, `elyo_health`, `elyo_reporting`, Audit-DB).
  - Jedes Backup verschlüsselt mit domänengetrenntem Schlüssel (12.2); gemeinsamer Storage-Ort mit getrennten Prefixen.
  - Einheitliche Retention: 90 Tage (abgestimmt auf die Backup-Löschfrist aus 11.4).
  - Kein zusätzlicher Cluster-Snapshot im Pilot.
- **Begründung:** Domänengetrennte Dumps setzen die Trennungsidee auch im Backup fort; ein Cluster-Snapshot wäre ein einzelnes Artefakt mit allen Domänen inkl. Mapping.
- **Folgeaufgabe:** Backup-Jobs und Verschlüsselung einrichten; Retention-Monitoring.
- **ADR-relevant:** JA

## 16.2 Konsistenz bei Restore

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Restore erfolgt grundsätzlich für alle Domänen auf denselben Backup-Zeitpunkt.
  - Einzel-Domänen-Restore nur im Ausnahmefall, gefolgt von einem Reconciliation-Job (verwaiste Mappings/Subjects bereinigen).
  - Nach jedem Restore wird die Löschliste (11.4) erneut angewendet, damit gelöschte Nutzer gelöscht bleiben.
  - Die Audit-DB wird nie zurückgesetzt, sondern nur vorwärts ergänzt; der Restore selbst wird auditiert (10.1).
- **Begründung:** Gemeinsamer Zeitpunkt verhindert hängende Referenzen zwischen den Domänen; der Löschlisten-Check schließt die DSGVO-Lücke beim Restore.
- **Folgeaufgabe:** Restore-Runbook inkl. Reconciliation-Job und Löschlisten-Check schreiben.
- **ADR-relevant:** JA

## 16.3 Restore-Tests

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Quartalsweiser Restore-Test in eine isolierte Umgebung.
  - Verantwortlich: Tech-Lead; jedes Testergebnis wird dokumentiert.
  - Pilot-Ziele: RPO 24 Stunden (tägliche Backups, 16.1), RTO 24 Stunden.
- **Begründung:** Quartalsrhythmus ist für ein kleines Team realistisch und deckt schleichende Backup-Defekte rechtzeitig auf; die Ziele passen zum täglichen Backup-Zyklus.
- **Folgeaufgabe:** Ersten Restore-Test vor Pilotstart durchführen und Vorlage für das Testprotokoll erstellen.
- **ADR-relevant:** Grundprinzip JA

---

# 17. Ownership und Governance

## 17.1 Domänen-Ownership

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Der Tech-Lead ist technischer und vorläufig auch fachlicher Owner der Health-Logik.
  - Freigaben:
    - neue Health-Datenfelder → Tech-Lead
    - neue Reporting-Kennzahlen → Tech-Lead + interne Kontrollperson (Allowlist-Prinzip, 8.5)
    - neue Mapping-Operationen → Tech-Lead + Kontrollperson + ADR-Update
  - Konflikte zwischen Produkt, Technik und Privacy entscheidet im Pilot der Tech-Lead; Privacy-Bedenken der Kontrollperson haben Vetorecht.
- **Begründung:** Handlungsfähigkeit im Pilot bei gleichzeitiger Gewaltenteilung an den kritischen Stellen (Reporting, Mapping).
- **Folgeaufgabe:** Nach dem Pilot fachlich-medizinischen Owner benennen.
- **ADR-relevant:** JA

## 17.2 Änderungsprozess

- **Status:** DECIDED
- **Entscheidung / Antwort:** Leichtgewichtiger Prozess mit Pflichttriggern:
  - Pflicht-Privacy-Review durch die Kontrollperson bei: jeder neuen Reporting-Kennzahl, jeder neuen Mapping-Operation, jedem Cross-Domain-Zugriff.
  - DB-Rollenänderungen werden im Änderungsprotokoll dokumentiert.
  - Zeitlich begrenzte Ausnahmen nur mit definiertem Enddatum und Ticket.
  - Alle übrigen Änderungen laufen im normalen Entwicklungsprozess (Code-Review).
- **Begründung:** Die Pflichttrigger decken genau die Stellen ab, an denen die Architekturgarantien brechen könnten, ohne ADR-Müdigkeit zu erzeugen.
- **Folgeaufgabe:** Trigger-Checkliste in den PR-/Review-Prozess integrieren.
- **ADR-relevant:** JA

---

# 18. Privacy-Abnahme

## 18.1 Verantwortliche zweite Instanz

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Die Privacy-Abnahme der ADR übernimmt die interne zweite Kontrollperson (9.3).
  - Externe Beratung ist vor dem ersten Pilotkunden nicht zwingend vorgesehen.
  - Die Datenschutz-Folgenabschätzung (DSFA) wird parallel zum Pilot nachgezogen, nicht als Blocker davor.
  - Hinweis/Risiko: Bei Gesundheitsdaten im Arbeitgeberkontext ist eine DSFA nach Art. 35 DSGVO regelmäßig vor Verarbeitungsbeginn erforderlich; das verbleibende rechtliche Risiko wird bewusst getragen und die DSFA mit höchster Priorität erstellt.
- **Begründung:** Schneller Pilotstart ohne externe Abhängigkeiten; das Restrisiko ist dokumentiert und terminiert.
- **Folgeaufgabe:** DSFA-Erstellung als eigenes Ticket mit Zieltermin vor bzw. unmittelbar zu Pilotbeginn.
- **ADR-relevant:** JA

## 18.2 Abnahmeprotokoll

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Abgenommene Artefakte: ADR, Suppression-Konzept (8.x), Rollen-/Access-Matrix (6.2/9.1), Löschkonzept (11.4).
  - Das Protokoll ist eine versionierte Markdown-Datei im Repository mit Version, Datum, Beteiligten und ggf. offenen Bedingungen (jeweils mit Frist).
  - Offene Bedingungen sind zulässig, solange sie terminiert und im Protokoll dokumentiert sind (z. B. DSFA-Nachreichung, 18.1).
  - Erneute Prüfung erfolgt bei den Pflichttriggern aus 17.2.
- **Begründung:** Eine Repo-Datei ist versionierbar, im Arbeitsfluss und bleibt aktuell; formale Signaturen können bei Kundenbedarf ergänzt werden.
- **Folgeaufgabe:** Protokoll-Template anlegen und erste Abnahme mit der Kontrollperson durchführen.
- **ADR-relevant:** JA

---

# 19. Security- und Architekturtests

## 19.1 Automatisierte Boundary-Tests

- **Status:** DECIDED
- **Mindestens zu prüfen:**
  - Company Runtime kann Mapping nicht lesen
  - Company Runtime kann Health nicht lesen
  - Identity Runtime kann Health nicht lesen
  - Health Runtime kann Identity nicht frei lesen
  - Reporting Runtime kann keine personenbezogenen Rohdaten lesen
  - keine Runtime besitzt alle Credentials
  - kein ORM-Modell überschreitet die Boundary
  - Mapping-Aufrufe erzeugen Audit-Ereignisse
  - widerrufene Memberships erscheinen nicht in neuen Reports
  - unterdrückte Aggregate werden nicht ausgegeben
  - Logs enthalten keine sensiblen Identifikatoren
- **Entscheidung / Antwort:**
  - Automatisierte Integrationstests starten jede Runtime mit ihren echten PostgreSQL-Rollen und prüfen sämtliche oben gelisteten Boundary-Fälle.
  - Die Liste aus diesem Punkt wird 1:1 zur Testspezifikation.
- **Begründung:** Nur Tests gegen die echten Grants beweisen, dass die Trennung zur Laufzeit tatsächlich durchgesetzt wird – nicht nur im Anwendungscode.
- **Folgeaufgabe:** Security Test Specification aus der Liste ableiten und Tests implementieren.
- **ADR-relevant:** JA

## 19.2 CI- und Infrastrukturtests

- **Status:** DECIDED
- **Entscheidung / Antwort:**
  - Alle Boundary-Tests (19.1) laufen bei jedem Merge in der CI.
  - Die CI startet PostgreSQL mit den echten Rollen-Grants aus denselben Migrationen wie Produktion.
  - Das Docker-Setup wird per Test auf Credential-Trennung geprüft (keine Runtime besitzt alle Secrets).
  - Nur die Restore-Tests (16.3) bleiben manuell.
- **Begründung:** Die zentralen Architekturgarantien werden kontinuierlich abgesichert; Boundary-Brüche fallen sofort im PR auf statt im Betrieb.
- **Folgeaufgabe:** CI-Pipeline mit Rollen-Setup aufbauen.
- **ADR-relevant:** JA

---

# 20. ADR-Readiness-Check

Die ADR kann erstellt werden, wenn für alle folgenden Punkte mindestens eine belastbare Grundrichtung vorliegt:

- [x] 3.1 Provisionierungsworkflow
- [x] 3.2 Nutzung ohne aktive Membership
- [x] 4.1 Sponsor-/Entitlement-Grundmodell
- [x] 4.2 Leistungsumfang ohne Sponsor
- [x] 5.1 Mapping-Operationen
- [x] 5.2 Mapping-Statusmodell
- [x] 5.3 Re-Identifizierung
- [x] 6.1 Runtime-Aufteilung
- [x] 6.2 Credential-Isolation
- [x] 6.3 Runtime-Kommunikation
- [x] 7.1 Zeitliche Datennutzung
- [x] 7.2 Snapshot-Regeln
- [x] 7.3 Kohortenbildung
- [x] 7.4 Reporting Tenant Identifier
- [x] 8.1 Mindestschwelle
- [x] 8.2 Zählerdefinition
- [x] 8.3 Kleine Kategorien
- [x] 8.4 Differenzangriffe
- [x] 8.5 Nicht reportbare Kennzahlen
- [x] 9.1 Rollenmodell
- [x] 9.2 Pilot-Sonderzugriff
- [x] 9.3 Zweite Kontrollperson
- [x] 10.1–10.3 Audit-Grundmodell
- [x] 11.1–11.5 Lifecycle-Grundregeln
- [x] 12.1–12.2 Verschlüsselungsstandard
- [x] 13.1–13.3 Dokument-Grundschutz
- [x] 14.1–14.3 Datenklassifikation
- [x] 15.1–15.3 Demo-Risikoinventar
- [x] 16.1–16.3 Backup-/Restore-Grundregeln
- [x] 17.1–17.2 Ownership und Governance
- [x] 18.1–18.2 Privacy-Abnahme
- [x] 19.1–19.2 Security- und Architekturtests

---

# 21. Änderungsprotokoll

| Datum | Referenz | Änderung | Autor |
|---|---|---|---|
| 2026-07-10 | 0–21 | Initiale Erstellung mit bisherigen Entscheidungen | Tech-Lead / ChatGPT |
| 2026-07-12 | 3–19 | Alle offenen Punkte (OPEN/PARTIALLY_DECIDED) entschieden und dokumentiert; ADR-Readiness-Check vollständig abgehakt | Tech-Lead / Claude |
