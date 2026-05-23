# ELYO Current Handoff

## Date
Sat May 23 20:01:32 CEST 2026

## Working Directory
/Users/bjornmohr/PhpstormProjects/ELYO_TARGET

## Git Branch
main

## Git Status
AM scripts/bootstrap-codex-agent-files.sh
?? .ai/
?? .codex/
?? .idea/phpunit.xml
?? AGENTS.md
?? apps/api-laravel/database/migrations/2026_05_17_120000_add_created_by_to_surveys_table.php
?? docs/ai-context/
?? docs/ai-handoff/
?? docs/ai-prompts/
?? docs/ai-tasks/
?? scripts/codex-plan.sh
?? scripts/codex-review.sh
?? scripts/codex-task.sh
?? scripts/create-handoff.sh

## Recent Commits
3ae6c2c Added logic for setting surveys etc to active and edit them
588dc86 Rewrote all compoents to angular22 syntax
e84a01c Added missing files
9bb4d3d Add feedback after clicking button to save/create
2e2c841 new readme
ec5924b Fixed small error
4d1e2ad Fixed small error
b6fbfd5 Fixed couple of errors
c8e3759 Points can now be changed in the platform admin view
f1ae3c2 Added amnesis form to being edited by user. Also check that daily check in is not done again and again.

## Docker Compose Config Check
docker compose config: OK

## Laravel Routes

  GET|HEAD  / ............................................... routes/web.php:5
  GET|HEAD  api/admin/companies ........... Admin\AdminCompanyController@index
  POST      api/admin/companies ........... Admin\AdminCompanyController@store
  GET|HEAD  api/admin/companies/{company} .. Admin\AdminCompanyController@show
  PUT       api/admin/companies/{company} Admin\AdminCompanyController@update
  POST      api/admin/companies/{company}/invite-company-admin Admin\AdminCom…
  GET|HEAD  api/admin/partners ............ Admin\AdminPartnerController@index
  PATCH     api/admin/partners/{id} ...... Admin\AdminPartnerController@update
  GET|HEAD  api/admin/points-config ........ Admin\AdminPointsController@index
  PUT       api/admin/points-config ....... Admin\AdminPointsController@update
  POST      api/auth/invite/accept .............. Auth\InviteController@accept
  GET|HEAD  api/auth/invite/verify .............. Auth\InviteController@verify
  POST      api/auth/login ......................... Auth\AuthController@login
  POST      api/auth/logout ....................... Auth\AuthController@logout
  GET|HEAD  api/auth/me ............................... Auth\AuthController@me
  GET|HEAD  api/company/dashboard ........ Company\CompanyController@dashboard
  GET|HEAD  api/company/invitations Company\CompanyInvitationController@invit…
  POST      api/company/invitations Company\CompanyInvitationController@store…
  DELETE    api/company/invitations/{invite} Company\CompanyInvitationControl…
  GET|HEAD  api/company/measures ............. Company\MeasureController@index
  POST      api/company/measures ............. Company\MeasureController@store
  PATCH     api/company/measures/{id} ....... Company\MeasureController@update
  GET|HEAD  api/company/reports ............... Company\ReportController@index
  GET|HEAD  api/company/surveys ........ Company\CompanySurveyController@index
  POST      api/company/surveys ........ Company\CompanySurveyController@store
  GET|HEAD  api/company/surveys/{id} .... Company\CompanySurveyController@show
  PATCH     api/company/surveys/{id} .. Company\CompanySurveyController@update
  DELETE    api/company/surveys/{id} . Company\CompanySurveyController@destroy
  POST      api/company/surveys/{id}/activate Company\CompanySurveyController…
  GET|HEAD  api/company/surveys/{id}/results Company\CompanySurveyController@…
  GET|HEAD  api/company/teams ................... Company\TeamController@index
  POST      api/company/teams ................... Company\TeamController@store
  GET|HEAD  api/company/teams/{id} ............... Company\TeamController@show
  PUT       api/company/teams/{id} ............. Company\TeamController@update
  DELETE    api/company/teams/{id} ............ Company\TeamController@destroy
  GET|HEAD  api/company/teams/{teamId}/members Company\TeamController@members
  GET|HEAD  api/company/users ...... Company\CompanyInvitationController@users
  POST      api/employee/checkin ......... Employee\EmployeeController@checkin
  GET|HEAD  api/employee/checkin/status Employee\EmployeeController@checkinSt…
  GET|HEAD  api/employee/dashboard ..... Employee\EmployeeController@dashboard
  POST      api/employee/documents Employee\EmployeeController@uploadDocument
  GET|HEAD  api/employee/history ......... Employee\EmployeeController@history
  GET|HEAD  api/employee/measures ....... Employee\EmployeeController@measures
  GET|HEAD  api/employee/profile ...... Employee\EmployeeController@getProfile
  PUT       api/employee/profile ... Employee\EmployeeController@updateProfile
  GET|HEAD  api/employee/surveys ............. Employee\SurveyController@index
  GET|HEAD  api/employee/surveys/{id} ......... Employee\SurveyController@show
  POST      api/employee/surveys/{id}/respond Employee\SurveyController@respo…
  GET|HEAD  api/employee/surveys/{id}/result Employee\SurveyController@result
  GET|HEAD  api/health ..................................... routes/api.php:22
  POST      api/partner/documents ......................... routes/api.php:118
  POST      api/partner/login ............ Partner\PartnerAuthController@login
  POST      api/partner/logout .......... Partner\PartnerAuthController@logout
  GET|HEAD  api/partner/me .................. Partner\PartnerAuthController@me
  POST      api/partner/register ...... Partner\PartnerAuthController@register
  GET|HEAD  sanctum/csrf-cookie sanctum.csrf-cookie › Laravel\Sanctum › CsrfC…
  GET|HEAD  storage/{path} storage.local › vendor/laravel/framework/src/Illum…
  PUT       storage/{path} storage.local.upload › vendor/laravel/framework/sr…
  GET|HEAD  up vendor/laravel/framework/src/Illuminate/Foundation/Configurati…

                                                           Showing [59] routes


## Laravel Tests

  [30;42;1m PASS [39;49;22m[39m Tests\Unit\ExampleTest[39m
  [32;1m✓[39;22m[90m [39m[90mthat true is true[39m

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\AuthTest[39m
  [32;1m✓[39;22m[90m [39m[90muser can login with correct credentials[39m[90m                             [39m [90m0.42s[39m  
  [32;1m✓[39;22m[90m [39m[90muser cannot login with incorrect password[39m[90m                           [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mlogin does not reveal email existence[39m[90m                               [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90minactive user cannot login[39m[90m                                          [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mlogin to company portal fails for employee only user[39m[90m                [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mlogin to employee portal fails for company only user[39m[90m                [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mauthenticated user can get me info[39m[90m                                  [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90melyo admin can create company[39m[90m                                       [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mnon admin cannot create company[39m[90m                                     [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee cannot create company[39m[90m                                      [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90melyo admin can invite first company admin[39m[90m                           [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90melyo admin can manage points config[39m[90m                                 [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany admin can invite employee[39m[90m                                   [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany admin cannot invite into another company[39m[90m                    [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee cannot invite users[39m[90m                                        [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee cannot access company dashboard[39m[90m                            [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany admin cannot access another companys data[39m[90m                   [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90minvite accept creates user with correct role[39m[90m                        [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90minvite accept cannot override role[39m[90m                                  [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90minvite accept cannot override company[39m[90m                               [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90minvite for email already in another company is rejected[39m[90m             [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mexpired invite cannot be accepted[39m[90m                                   [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mused invite cannot be accepted twice[39m[90m                                [39m [90m0.01s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\CompanyTest[39m
  [32;1m✓[39;22m[90m [39m[90mcompany dashboard aggregation and threshold[39m[90m                         [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany dashboard participation counts distinct active employees[39m[90m    [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany dashboard participation is capped at one hundred percent[39m[90m    [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanager scoping to team[39m[90m                                             [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90msurvey results anonymity threshold[39m[90m                                  [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanager only sees survey results for their team[39m[90m                     [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mdraft surveys can be edited and activated by allowed owner[39m[90m          [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mmeasure creation and transitions[39m[90m                                    [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanager can see global and managed team measures[39m[90m                    [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompany can create team with manager and survey with dates[39m[90m          [39m [90m0.02s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\DatabaseMigrationTest[39m
  [32;1m✓[39;22m[90m [39m[90mmigrations run successfully[39m[90m                                         [39m [90m0.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mcan create core models[39m[90m                                              [39m [90m0.01s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\EmployeeTest[39m
  [32;1m✓[39;22m[90m [39m[90memployee can get dashboard data[39m[90m                                     [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can submit checkin[39m[90m                                         [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can submit checkin only once per day[39m[90m                       [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can get history[39m[90m                                            [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can update profile[39m[90m                                         [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can list surveys[39m[90m                                           [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can get survey details[39m[90m                                     [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can respond to survey[39m[90m                                      [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can always view own survey result[39m[90m                          [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can list relevant measures[39m[90m                                 [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90memployee can upload medical pdf[39m[90m                                     [39m [90m0.04s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\ExampleTest[39m
  [32;1m✓[39;22m[90m [39m[90mthe application returns a successful response[39m[90m                       [39m [90m0.02s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\IntegrationTest[39m
  [32;1m✓[39;22m[90m [39m[90mpartner registration and login[39m[90m                                      [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90madmin can review partner[39m[90m                                            [39m [90m0.01s[39m  

  [90mTests:[39m    [32;1m50 passed[39;22m[90m (158 assertions)[39m
  [90mDuration:[39m [39m1.57s[39m


## Angular Build

> web-angular@0.0.0 build
> ng build

❯ Building...
✔ Building...
Initial chunk files | Names                         |  Raw size | Estimated transfer size
chunk-RW7XUXVS.js   | -                             | 143.92 kB |                42.85 kB
chunk-45T46WZF.js   | -                             |  87.80 kB |                22.23 kB
chunk-2UWLWHMF.js   | -                             |  34.93 kB |                 9.95 kB
styles-SA3DQPFK.css | styles                        |  30.57 kB |                 5.17 kB
main-IZD5QDCT.js    | main                          |   7.89 kB |                 2.35 kB
chunk-H3N7PNGO.js   | -                             |   2.88 kB |               921 bytes
chunk-MTPEZV6L.js   | -                             | 776 bytes |               776 bytes

                    | Initial total                 | 308.76 kB |                84.24 kB

Lazy chunk files    | Names                         |  Raw size | Estimated transfer size
chunk-JGVLHZGJ.js   | -                             |  48.57 kB |                 9.58 kB
chunk-5PRDC6RE.js   | company-surveys-component     |  20.44 kB |                 5.28 kB
chunk-VK7TDTDO.js   | profile-component             |   9.22 kB |                 2.73 kB
chunk-R3WG5XR4.js   | company-measures-component    |   7.68 kB |                 2.48 kB
chunk-HJ6T2RB7.js   | surveys-component             |   7.53 kB |                 2.32 kB
chunk-HXAMCX73.js   | checkin-component             |   7.01 kB |                 2.27 kB
chunk-XAZMFODP.js   | company-teams-component       |   6.58 kB |                 2.31 kB
chunk-HRYTFBJR.js   | company-invitations-component |   6.49 kB |                 2.23 kB
chunk-XPGUTMQM.js   | invite-component              |   6.47 kB |                 2.33 kB
chunk-MTOVNW2R.js   | company-shell-component       |   5.42 kB |                 1.65 kB
chunk-X76U4UCG.js   | dashboard-component           |   5.01 kB |                 1.80 kB
chunk-I4SZ75OI.js   | companies-create-component    |   4.73 kB |                 1.74 kB
chunk-24ORSUSR.js   | login-component               |   4.56 kB |                 1.87 kB
chunk-IQQZ2JOZ.js   | employee-shell-component      |   4.42 kB |                 1.44 kB
chunk-H5DA6KQS.js   | dashboard-component           |   4.28 kB |                 1.70 kB
...and 14 more lazy chunks files. Use "--verbose" to show all the files.

Application bundle generation complete. [2.122 seconds] - 2026-05-23T18:01:37.565Z

Output location: /app/dist/web-angular

