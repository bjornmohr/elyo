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
        <h1 class="text-xl font-bold text-slate-800">Health Profile</h1>
      </header>

      <div *ngIf="profile()?.anamnesisDue" class="bg-amber-50 border border-amber-100 text-amber-800 rounded-2xl p-4 text-sm">
        Your anamnesis data is due for a refresh. Please review and update it.
      </div>

      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 bg-slate-50 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-800">Anamnesis Data</h2>
          <p class="text-sm text-slate-500">Saved data is always visible here. Completion can award points.</p>
        </div>

        <form (ngSubmit)="save()" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Name</span>
            <input [(ngModel)]="form.name" name="name" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Birth Year</span>
            <input type="number" [(ngModel)]="form.birthYear" name="birthYear" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Biological Sex</span>
            <select [(ngModel)]="form.biologicalSex" name="biologicalSex" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="PREFER_NOT_TO_SAY">Prefer not to say</option>
              <option value="MALE">Male</option>
              <option value="FEMALE">Female</option>
              <option value="OTHER">Other</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Activity Level</span>
            <select [(ngModel)]="form.activityLevel" name="activityLevel" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Not set</option><option>LOW</option><option>MEDIUM</option><option>HIGH</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Sleep Quality</span>
            <select [(ngModel)]="form.sleepQuality" name="sleepQuality" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Not set</option><option>POOR</option><option>OKAY</option><option>GOOD</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Stress Tendency</span>
            <select [(ngModel)]="form.stressTendency" name="stressTendency" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Not set</option><option>LOW</option><option>MEDIUM</option><option>HIGH</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Smoking Status</span>
            <select [(ngModel)]="form.smokingStatus" name="smokingStatus" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
              <option value="">Not set</option><option>NEVER</option><option>FORMER</option><option>CURRENT</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-slate-600">Nutrition Type</span>
            <input [(ngModel)]="form.nutritionType" name="nutritionType" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
            <input type="checkbox" [(ngModel)]="form.hasMedication" name="hasMedication" class="rounded border-slate-300 text-teal-600">
            Regular medication
          </label>
          <div class="md:col-span-2 flex items-center justify-between pt-2">
            <span class="text-sm text-slate-500">Completion: {{ profile()?.anamnesis?.completionPct ?? 0 }}%</span>
            <button type="submit" class="px-5 py-3 bg-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-teal-100">Save anamnesis</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 bg-slate-50 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-800">Medical PDFs</h2>
          <p class="text-sm text-slate-500">Upload additional medical documents. Uploads can award points.</p>
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
      this.uploadError.set('Only PDF files can be uploaded.');
      return;
    }
    this.employeeService.uploadDocument(file).subscribe({
      next: (res: any) => this.documents.update(documents => [res.data, ...documents]),
      error: err => this.uploadError.set(err.error?.message || 'Upload failed.'),
    });
  }
}
