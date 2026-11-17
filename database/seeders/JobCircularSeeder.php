<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobCircular;
use Carbon\Carbon;

class JobCircularSeeder extends Seeder
{
    public function run(): void
    {
        $circulars = [
            [
                'title' => 'সহকারী প্রোগ্রামার (Assistant Programmer - Grade 9)',
                'organization' => 'তথ্য ও যোগাযোগ প্রযুক্তি অধিদপ্তর (ICT Division)',
                'category' => 'govt',
                'location' => 'ঢাকা, বাংলাদেশ',
                'job_type' => 'full-time',
                'experience_level' => 'Grade 9 (Fresher / 0-2 yrs)',
                'salary_range' => '৳২২,০০০ - ৳৫৩,০৬০ (National Pay Scale 2015)',
                'application_deadline' => Carbon::now()->addDays(18),
                'source_url' => 'http://doict.teletalk.com.bd',
                'circular_pdf_url' => 'https://doict.portal.gov.bd/sites/default/files/files/doict.portal.gov.bd/notices/recruitment_notice.pdf',
                'description' => 'তথ্য ও যোগাযোগ প্রযুক্তি বিভাগের আওতাধীন আইসিটি অধিদপ্তরে রাজস্ব খাতভুক্ত ৯ম গ্রেডের সহকারী প্রোগ্রামার পদে স্থায়ী ভিত্তিতে কর্মকর্তা নিয়োগ দেওয়া হবে। প্রার্থীদের কম্পিউটার সায়েন্স, আইটি বা সংশ্লিষ্ট বিষয়ে ন্যূনতম দ্বিতীয় শ্রেণি বা সিজিপিএ সমমানের স্নাতক ডিগ্রি থাকতে হবে।',
                'requirements' => 'শিক্ষাগত যোগ্যতা: কোনো স্বীকৃত বিশ্ববিদ্যালয় হতে কম্পিউটার সায়েন্স / কম্পিউটার সাইন্স অ্যান্ড ইঞ্জিনিয়ারিং / ইনফরমেশন অ্যান্ড কমিউনিকেশন টেকনোলজি বিষয়ে স্নাতক (সম্মান) বা সমমানের ডিগ্রি। বয়সসীমা: অনুর্ধ্ব ৩২ বছর।',
                'skills_tags' => ['PHP', 'Laravel', 'PostgreSQL', 'Computer Networks', 'Database Management', 'BSc in CSE'],
                'source_type' => 'admin',
                'is_featured' => true,
                'is_active' => true,
                'views_count' => 142
            ],
            [
                'title' => 'Assistant Director (General) - 100 Posts',
                'organization' => 'বাংলাদেশ ব্যাংক (Bangladesh Bank)',
                'category' => 'bank',
                'location' => 'ঢাকা (Central Bank HQ)',
                'job_type' => 'full-time',
                'experience_level' => 'Entry Level / Grade 9',
                'salary_range' => '৳২২,০০০ - ৳৫৩,০৬০ + Allowances',
                'application_deadline' => Carbon::now()->addDays(24),
                'source_url' => 'https://erecruitment.bb.org.bd',
                'circular_pdf_url' => 'https://erecruitment.bb.org.bd/career/jobnotice.pdf',
                'description' => 'Bangladesh Bank invites online applications from Bangladeshi citizens for the position of Assistant Director (General). Selected candidates will undergo probationary training and contribute to monetary policy, banking regulation, and macro-financial supervision.',
                'requirements' => 'Four-year Honors or Master’s degree from a recognized university in Economics, Finance, Accounting, Management, CSE, Mathematics, or Statistics with at least two first divisions/classes.',
                'skills_tags' => ['Financial Analysis', 'Analytical Ability', 'Macroeconomics', 'Business Writing', 'Banking Regulations'],
                'source_type' => 'admin',
                'is_featured' => true,
                'is_active' => true,
                'views_count' => 389
            ],
            [
                'title' => 'Senior Full Stack Engineer (React + Node/Laravel)',
                'organization' => 'Pathao Bangladesh',
                'category' => 'private',
                'location' => 'Gulshan-1, Dhaka (Hybrid)',
                'job_type' => 'full-time',
                'experience_level' => '3 - 6 Years Experience',
                'salary_range' => '৳১,৩০,০০০ - ৳১,৮০,০০০ / month',
                'application_deadline' => Carbon::now()->addDays(12),
                'source_url' => 'https://pathao.com/careers',
                'circular_pdf_url' => null,
                'description' => 'Pathao is looking for an experienced Full Stack Engineer to architect high-throughput microservices, scale logistics engine components, and build reactive frontend dashboards with React and Tailwind CSS.',
                'requirements' => 'Strong experience in modern React, Tailwind CSS, TypeScript, RESTful API architecture in Node.js or Laravel, Redis caching, and Docker.',
                'skills_tags' => ['React', 'TypeScript', 'Node.js', 'Laravel', 'PostgreSQL', 'Docker', 'Redis'],
                'source_type' => 'admin',
                'is_featured' => true,
                'is_active' => true,
                'views_count' => 210
            ],
            [
                'title' => 'Remote AI Frontend Engineer',
                'organization' => 'Vercel Ecosystem Labs (Global Remote)',
                'category' => 'remote',
                'location' => 'Worldwide (100% Remote)',
                'job_type' => 'remote',
                'experience_level' => 'Mid to Senior Level',
                'salary_range' => '$85,000 - $120,000 / year (USD)',
                'application_deadline' => Carbon::now()->addDays(30),
                'source_url' => 'https://remoteok.com',
                'circular_pdf_url' => null,
                'description' => 'Join our global distributed engineering squad building generative AI chat interfaces, workflow orchestration studios, and real-time streaming tools using Next.js, React 19, and Tailwind.',
                'requirements' => 'Deep mastery of modern React, AI SDK (Vercel AI / OpenAI streaming protocols), Tailwind CSS, component architecture, and asynchronous state machines.',
                'skills_tags' => ['React', 'Next.js', 'Tailwind CSS', 'AI SDK', 'WebSockets', 'Remote Work'],
                'source_type' => 'api_remote',
                'is_featured' => false,
                'is_active' => true,
                'views_count' => 540
            ],
            [
                'title' => 'সহকারী রাজস্ব কর্মকর্তা (Assistant Revenue Officer - ARO)',
                'organization' => 'জাতীয় রাজস্ব বোর্ড (National Board of Revenue - NBR)',
                'category' => 'govt',
                'location' => 'সমগ্র বাংলাদেশ',
                'job_type' => 'full-time',
                'experience_level' => 'Grade 10 (Non-Cadre)',
                'salary_range' => '৳১৬,০০০ - ৳৩৮,৬৪০ (National Pay Scale)',
                'application_deadline' => Carbon::now()->addDays(7),
                'source_url' => 'http://bpsc.teletalk.com.bd',
                'circular_pdf_url' => 'http://www.bpsc.gov.bd/sites/default/files/files/bpsc.portal.gov.bd/notices/aro_circular.pdf',
                'description' => 'বাংলাদেশ সরকারী কর্ম কমিশন (BPSC) এর মাধ্যমে জাতীয় রাজস্ব বোর্ডের কাস্টমস ও ভ্যাট উইং-এ ১০ম গ্রেডের সহকারী রাজস্ব কর্মকর্তা পদে সাধারণ নাগরিক নিয়োগের বিশাল সার্কুলার।',
                'requirements' => 'স্নাতক বা সমমানের ডিগ্রি। কোনো ৩য় বিভাগ গ্রহণযোগ্য নয়। বয়স অনুর্ধ্ব ৩২ বছর।',
                'skills_tags' => ['General Knowledge', 'English Comprehension', 'Bangla Grammar', 'Basic Math', 'BPSC Syllabus'],
                'source_type' => 'admin',
                'is_featured' => false,
                'is_active' => true,
                'views_count' => 412
            ],
            [
                'title' => 'Software Quality Assurance (SQA) Engineer',
                'organization' => 'Brain Station 23',
                'category' => 'private',
                'location' => 'Mohakhali DOHS, Dhaka',
                'job_type' => 'full-time',
                'experience_level' => '1 - 3 Years',
                'salary_range' => '৳৪৫,০০০ - ৳৬৫,০০০ / month',
                'application_deadline' => Carbon::now()->subDays(5), // Expired demo test
                'source_url' => 'https://brainstation-23.com/careers',
                'circular_pdf_url' => null,
                'description' => 'Responsible for manual and automated test execution across enterprise fintech applications, defect tracking, and regression validation.',
                'requirements' => 'B.Sc in CSE/IT. Familiarity with Postman API testing, Cypress/Selenium, and JIRA issue workflows.',
                'skills_tags' => ['SQA', 'Postman', 'Cypress', 'JIRA', 'Manual Testing', 'API Testing'],
                'source_type' => 'admin',
                'is_featured' => false,
                'is_active' => true,
                'views_count' => 195
            ]
        ];

        foreach ($circulars as $item) {
            JobCircular::create($item);
        }
    }
}
