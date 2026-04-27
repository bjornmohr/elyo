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
    <div class="max-w-xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">
           ←
        </a>
        <h1 class="text-xl font-bold text-slate-800">My Profile</h1>
      </header>

      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 bg-slate-50 border-b border-slate-100 flex items-center space-x-4">
           <div class="w-16 h-16 rounded-full bg-teal-100 flex items-center justify-center text-3xl">👤</div>
           <div>
             <h2 class="text-lg font-bold text-slate-800">Personal Information</h2>
             <p class="text-sm text-slate-500">Help us personalize your experience.</p>
           </div>
        </div>

        <form (ngSubmit)="save()" class="p-8 space-y-6">
           <div class="space-y-2">
             <label class="text-sm font-semibold text-slate-600 ml-1">Birth Year</label>
             <input type="number" [(ngModel)]="profile().birthYear" name="birthYear"
                    class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-teal-500 outline-none">
           </div>

           <div class="space-y-2">
             <label class="text-sm font-semibold text-slate-600 ml-1">Biological Sex</label>
             <select [(ngModel)]="profile().biologicalSex" name="biologicalSex"
                     class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-teal-500 outline-none appearance-none">
                <option value="MALE">Male</option>
                <option value="FEMALE">Female</option>
                <option value="OTHER">Other</option>
                <option value="PREFER_NOT_TO_SAY">Prefer not to say</option>
             </select>
           </div>

           <div class="pt-4">
             <button type="submit"
                     class="w-full py-4 bg-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-teal-100 hover:bg-teal-700 transition-colors">
               Save Changes
             </button>
           </div>
        </form>
      </div>

      <div class="bg-orange-50 p-6 rounded-3xl border border-orange-100 flex items-start space-x-4">
         <span class="text-2xl">🔒</span>
         <div class="text-sm text-orange-800">
           <p class="font-bold mb-1">Your data is secure</p>
           <p>Only you can see your personal details. Your company only receives anonymous, aggregated health trends.</p>
         </div>
      </div>
    </div>
  `
})
export class ProfileComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  profile = signal<any>({
    birthYear: null,
    biologicalSex: 'PREFER_NOT_TO_SAY'
  });

  ngOnInit() {
    this.employeeService.getProfile().subscribe(data => {
      if (data) {
        this.profile.set({
          birthYear: data.birthYear,
          biologicalSex: data.biologicalSex || 'PREFER_NOT_TO_SAY'
        });
      }
    });
  }

  save() {
    this.employeeService.updateProfile(this.profile()).subscribe(() => {
      // Show success message or redirect
      alert('Profile updated successfully!');
    });
  }
}
