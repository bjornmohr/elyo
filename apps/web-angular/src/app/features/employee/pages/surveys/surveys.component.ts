import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EmployeeService, SurveyListItem, SurveyDetail, SurveyResult } from '../../services/employee.service';

@Component({
  selector: 'app-surveys',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">
           ←
        </a>
        <h1 class="text-xl font-bold text-slate-800">Surveys</h1>
      </header>

      <!-- List View -->
      <div *ngIf="!selectedSurvey()" class="space-y-4">
        <div *ngFor="let survey of surveys()"
             class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center justify-between group hover:border-teal-200 transition-colors">
          <div class="space-y-1">
            <div class="flex items-center space-x-2">
              <h2 class="font-bold text-slate-800">{{ survey.title }}</h2>
              <span *ngIf="survey.isCompleted" class="bg-teal-100 text-teal-700 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Completed</span>
            </div>
            <p class="text-sm text-slate-500">{{ survey.description }}</p>
          </div>
          <button (click)="selectSurvey(survey)"
                  class="bg-slate-50 group-hover:bg-teal-600 group-hover:text-white text-slate-400 p-3 rounded-xl transition-all disabled:opacity-30">
            →
          </button>
        </div>

        <div *ngIf="surveys().length === 0" class="text-center py-12 text-slate-400">
          No surveys available right now.
        </div>
      </div>

      <!-- Detail/Response View -->
      <div *ngIf="selectedSurvey() as survey" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="p-8 bg-slate-50 border-b border-slate-100">
             <h2 class="text-2xl font-bold text-slate-800">{{ survey.title }}</h2>
             <p class="text-slate-500 mt-1">{{ survey.description }}</p>
          </div>

          <div class="p-8 space-y-10">
            <div *ngFor="let q of survey.questions" class="space-y-4">
               <label class="block font-bold text-slate-800">{{ q.text }}</label>

               <!-- Scale Question -->
               <div *ngIf="q.type === 'SCALE'" class="space-y-4">
                  <input type="range" min="1" max="10" [(ngModel)]="answers[q.id]" class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-teal-600">
                  <div class="flex justify-between text-xs text-slate-400 font-bold uppercase tracking-widest">
                    <span>1</span><span>10</span>
                  </div>
               </div>

               <!-- Yes/No Question -->
               <div *ngIf="q.type === 'YES_NO'" class="flex space-x-4">
                 <button (click)="answers[q.id] = 'YES'"
                         [class.bg-teal-600]="answers[q.id] === 'YES'"
                         [class.text-white]="answers[q.id] === 'YES'"
                         class="flex-1 py-3 px-6 rounded-xl border border-slate-100 font-bold transition-all">Yes</button>
                 <button (click)="answers[q.id] = 'NO'"
                         [class.bg-teal-600]="answers[q.id] === 'NO'"
                         [class.text-white]="answers[q.id] === 'NO'"
                         class="flex-1 py-3 px-6 rounded-xl border border-slate-100 font-bold transition-all">No</button>
               </div>

               <!-- Text Question -->
               <div *ngIf="q.type === 'TEXT'">
                 <textarea [(ngModel)]="answers[q.id]"
                           class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-teal-500 outline-none min-h-[100px]"></textarea>
               </div>
            </div>

            <div class="flex space-x-4 pt-6">
              <button (click)="selectedSurvey.set(null)" class="flex-1 py-4 bg-slate-100 text-slate-600 font-bold rounded-2xl">Cancel</button>
              <button (click)="submit()" class="flex-[2] py-4 bg-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-teal-100">Submit Answers</button>
            </div>
          </div>
        </div>
      </div>

      <div *ngIf="selectedResult() as result" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="p-8 bg-slate-50 border-b border-slate-100">
             <h2 class="text-2xl font-bold text-slate-800">{{ result.title }}</h2>
             <p class="text-slate-500 mt-1">Your submitted answers</p>
          </div>
          <div class="p-8 space-y-5">
            <div *ngFor="let q of result.questions" class="rounded-2xl bg-slate-50 p-4">
              <div class="font-bold text-slate-800">{{ q.text }}</div>
              <div class="text-slate-600 mt-2">{{ answerLabel(q) }}</div>
            </div>
            <button (click)="selectedResult.set(null)" class="w-full py-4 bg-slate-100 text-slate-600 font-bold rounded-2xl">Back to surveys</button>
          </div>
        </div>
      </div>
    </div>
  `
})
export class SurveysComponent implements OnInit {
  private employeeService = inject(EmployeeService);

  surveys = signal<SurveyListItem[]>([]);
  selectedSurvey = signal<SurveyDetail | null>(null);
  selectedResult = signal<SurveyResult | null>(null);
  answers: { [key: string]: any } = {};

  ngOnInit() {
    this.loadSurveys();
  }

  loadSurveys() {
    this.employeeService.getSurveys().subscribe(list => {
      this.surveys.set(list);
    });
  }

  selectSurvey(survey: SurveyListItem) {
    this.selectedResult.set(null);
    this.selectedSurvey.set(null);

    if (survey.isCompleted) {
      this.employeeService.getSurveyResult(survey.id).subscribe(result => this.selectedResult.set(result));
      return;
    }

    this.employeeService.getSurvey(survey.id).subscribe(detail => {
      this.selectedSurvey.set(detail);
      // Initialize answers
      detail.questions.forEach(q => {
        if (q.type === 'SCALE') this.answers[q.id] = 5;
        else this.answers[q.id] = '';
      });
    });
  }

  answerLabel(question: any) {
    const answer = question.answer ?? {};
    if (question.type === 'SCALE') return answer.scaleValue ?? '—';
    if (question.type === 'YES_NO') return answer.boolValue === true ? 'Yes' : answer.boolValue === false ? 'No' : '—';
    if (question.type === 'MULTIPLE_CHOICE') return answer.choiceValue ?? '—';
    return answer.textValue ?? '—';
  }

  submit() {
    const survey = this.selectedSurvey();
    if (!survey) return;

    const questionById = new Map(survey.questions.map(q => [q.id, q]));
    const payload = Object.entries(this.answers).map(([questionId, value]) => {
      const question = questionById.get(questionId);
      const answer: any = { questionId };
      if (question?.type === 'SCALE') answer.scaleValue = Number(value);
      else if (question?.type === 'YES_NO') answer.boolValue = value === 'YES';
      else if (question?.type === 'MULTIPLE_CHOICE') answer.choiceValue = String(value);
      else answer.textValue = String(value);
      return answer;
    });

    this.employeeService.submitSurveyResponse(survey.id, payload).subscribe(() => {
      alert('Thank you for your feedback!');
      this.selectedSurvey.set(null);
      this.loadSurveys();
    });
  }
}
