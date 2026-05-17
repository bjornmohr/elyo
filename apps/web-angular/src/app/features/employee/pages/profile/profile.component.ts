import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
        <h1 class="text-xl font-bold text-slate-800">Gesundheitsprofil</h1>
      </header>

      <div *ngIf="profile()?.anamnesisDue" class="bg-amber-50 border border-amber-100 text-amber-800 rounded-2xl p-4 text-sm">
        Deine Anamnese-Daten sind zur Aktualisierung fällig. Bitte prüfe sie und aktualisiere sie.
      </div>

      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 bg-slate-50 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-800">Anamnese-Daten</h2>
          <p class="text-sm text-slate-500">Gespeicherte Daten sind hier immer sichtbar. Eine vollständige Anamnese kann Punkte bringen.</p>
        </div>

        <form (ngSubmit)="save()" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Name</span>
            <input [(ngModel)]="form.name" name="name" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Geburtsjahr</span>
            <input type="number" [(ngModel)]="form.birthYear" name="birthYear" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Biologisches Geschlecht</span>
            <select [(ngModel)]="form.biologicalSex" name="biologicalSex" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="PREFER_NOT_TO_SAY">Keine Angabe</option>
              <option value="MALE">Männlich</option>
              <option value="FEMALE">Weiblich</option>
              <option value="OTHER">Divers</option>
            </select>
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Aktivitätsniveau</span>
            <select [(ngModel)]="form.activityLevel" name="activityLevel" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Nicht gesetzt</option><option>NIEDRIG</option><option>MITTEL</option><option>HOCH</option>
            </select>
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Schlafqualität</span>
            <select [(ngModel)]="form.sleepQuality" name="sleepQuality" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Nicht gesetzt</option><option>SCHLECHT</option><option>OKAY</option><option>GUT</option>
            </select>
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Stressneigung</span>
            <select [(ngModel)]="form.stressTendency" name="stressTendency" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Nicht gesetzt</option><option>NIEDRIG</option><option>MITTEL</option><option>HOCH</option>
            </select>
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Rauchstatus</span>
            <select [(ngModel)]="form.smokingStatus" name="smokingStatus" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Nicht gesetzt</option><option>NIE</option><option>FRÜHER</option><option>AKTUELL</option>
            </select>
          </label>
          <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Ernährungsform</span>
            <input [(ngModel)]="form.nutritionType" name="nutritionType" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
            <input type="checkbox" [(ngModel)]="form.hasMedication" name="hasMedication" class="rounded border-slate-300 text-teal-600">
            Regelmäßige Medikamente
          </label>
          <div class="md:col-span-2 flex items-center justify-between pt-2">
            <span class="text-sm text-slate-500">Vollständigkeit: {{ profile()?.anamnesis?.completionPct ?? 0 }}%</span>
            <button type="submit" class="px-5 py-3 bg-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-teal-100">Anamnese speichern</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 bg-slate-50 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-800">Medizinische PDFs</h2>
          <p class="text-sm text-slate-500">Lade zusätzliche medizinische Dokumente hoch. Uploads können Punkte bringen.</p>
        </div>
        <div class="p-6 space-y-4">
          <input type="file" accept="application/pdf" (change)="upload($event)" class="block w-full text-sm text-slate-500">
          <p *ngIf="uploadError()" class="text-sm text-red-600">{{ uploadError() }}</p>
          <div *ngFor="let document of documents()" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
            <span class="font-medium text-slate-700">{{ document.fileName }}</span>
            <span class="text-xs text-slate-400">{{ document.uploadedAt | date:'mediumDate' }}</span>
          </div>
        </div>
      </div>
    </div>
  `
})
export class ProfileComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  profile = signal<any>(null);
  documents = signal<any[]>([]);
  uploadError = signal<string | null>(null);

  form: any = {
    name: '',
    birthYear: null,
    biologicalSex: 'PREFER_NOT_TO_SAY',
    activityLevel: '',
    sleepQuality: '',
    stressTendency: '',
    smokingStatus: '',
    nutritionType: '',
    chronicPatterns: [],
    hasMedication: false,
  };

  ngOnInit() {
    this.employeeService.getProfile().subscribe((res: any) => {
      const data = res.data;
      const anamnesis = data.anamnesis ?? {};
      this.profile.set(data);
      this.documents.set(data.documents ?? []);
      this.form = {
        name: data.name,
        birthYear: anamnesis.birthYear ?? null,
        biologicalSex: anamnesis.biologicalSex ?? 'PREFER_NOT_TO_SAY',
        activityLevel: anamnesis.activityLevel ?? '',
        sleepQuality: anamnesis.sleepQuality ?? '',
        stressTendency: anamnesis.stressTendency ?? '',
        smokingStatus: anamnesis.smokingStatus ?? '',
        nutritionType: anamnesis.nutritionType ?? '',
        chronicPatterns: anamnesis.chronicPatterns ?? [],
        hasMedication: anamnesis.hasMedication ?? false,
      };
    });
  }

  save() {
    this.employeeService.updateProfile(this.form).subscribe((res: any) => {
      this.profile.set({ ...this.profile(), ...res.data });
    });
  }

  upload(event: Event) {
    this.uploadError.set(null);
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
      this.uploadError.set('Es können nur PDF-Dateien hochgeladen werden.');
      return;
    }
    this.employeeService.uploadDocument(file).subscribe({
      next: (res: any) => this.documents.update(documents => [res.data, ...documents]),
      error: err => this.uploadError.set(err.error?.message || 'Upload fehlgeschlagen.'),
    });
  }
}
