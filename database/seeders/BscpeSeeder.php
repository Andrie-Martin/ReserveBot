<?php

namespace Database\Seeders;

/**
 * BSCpE department data, from the BSCpE room schedule (Mon-Sat) and the
 * equipment request form: 11 rooms, 224 timetable entries (215 classes +
 * 9 blocked), 6 equipment categories and 63 items.
 *
 * Run with:  php artisan db:seed   (DatabaseSeeder calls this file)
 *
 * DATA NOTES (please confirm with the department):
 *  - Short titles were expanded, e.g. COMP ARCHI -> Computer Architecture.
 *  - Tue CEA313 6:00-9:00 PM says "CMPE 305 LOGIC CIRCUITS AND DESIGN".
 *    Entered as CMPE 304, the code Logic Circuits uses everywhere else.
 *  - CMPE 101 cells say "CPE 1-x"; entered as section "BSCPE 1-x".
 *    CMPE 101 has no title on the timetable, so its title is null.
 *  - Sat "CMPE 401 DATABASE SYSTEMS" (BSCPE 4-1) entered as
 *    Database Management Systems, like the other CMPE 401 sections.
 *  - CMPE 011 is "COMP PROG" for BSME/BSIE/BSREM but "PROG LOGIC AND
 *    DESIGN"/"PLD" for BSCPE 1-6 and 1-7. Kept as two courses.
 *  - "BSCOE 4-1P" and "BSCOE 4-3P" kept exactly as written.
 *  - Yellow cells (CEA302 "ECE" days, CEA207) and unlabeled light-green
 *    cells are entered as blocked time.
 *  - PLACEHOLDERS: semester dates, room capacities, equipment stock counts.
 */
class BscpeSeeder extends DepartmentSeeder
{
    protected function department(): array
    {
        return ['name' => 'Computer Engineering Department', 'slug' => 'bscpe', 'contact_email' => null];
    }

    // No bookingRules() here: the defaults fit this timetable
    // (Mon-Sat, 7:30 AM to 9:00 PM, 30-minute slots).

    protected function term(): array
    {
        // PLACEHOLDER DATES: replace with the official first and last day of classes
        return ['name' => '1st Semester AY 2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31'];
    }

    protected function rooms(): array
    {
        // [room, building, capacity]. Capacity is null until the real numbers are known.
        return [
            ['CEA300', 'CEA Building', null],
            ['CEA302', 'CEA Building', null],
            ['CEA310', 'CEA Building', null],
            ['CEA311', 'CEA Building', null],
            ['CEA312', 'CEA Building', null],
            ['CEA313', 'CEA Building', null],
            ['CEA314', 'CEA Building', null],
            ['CEA315', 'CEA Building', null],
            ['CEA316', 'CEA Building', null],
            ['CEA413', 'CEA Building', null],
            ['CEA207', 'CEA Building', null],
        ];
    }

    protected function timetable(): array
    {
        // [day, room, start, end, course code, course title, section, instructor]
        // Blocked time (room not available): [day, room, start, end, 'BLOCKED', reason]
        return [
            // ---------------- MONDAY ----------------
            ['Mon', 'CEA300', '10:30', '13:30', 'CMPE 402', 'Project Management', 'BSCPE 4-1', 'L. Reyes'],
            ['Mon', 'CEA300', '15:00', '18:00', 'CMPE 354', 'Enterprise Software', 'BSCPE 3-7', 'J. Tena'],
            ['Mon', 'CEA300', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-4', 'L. Reyes'],
            ['Mon', 'CEA302', '07:30', '21:00', 'BLOCKED', 'Used by ECE (yellow on timetable)'],
            ['Mon', 'CEA310', '10:30', '13:30', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-4', 'J. Tena'],
            ['Mon', 'CEA310', '15:00', '18:00', 'CMPE 402', 'Project Management', 'BSCPE 4-6', 'L. Reyes'],
            ['Mon', 'CEA310', '18:00', '21:00', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-2', 'J. Tena'],
            ['Mon', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay'],
            ['Mon', 'CEA311', '11:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-4', 'O. Pajabera'],
            ['Mon', 'CEA311', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSME 2-2', 'A. Racusa'],
            ['Mon', 'CEA311', '17:00', '20:00', 'CMPE 011', 'Computer Programming', 'BSME 2-2', 'A. Racusa'],
            ['Mon', 'CEA312', '11:30', '14:30', 'CMPE 310', 'Computer Networks', 'BSCPE 1-2P', 'J. Avila'],
            ['Mon', 'CEA312', '14:30', '17:30', 'CMPE 310', 'Computer Networks', 'BSCPE 1-2P', 'J. Avila'],
            ['Mon', 'CEA312', '18:00', '21:00', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-5', 'T. Velardo'],
            ['Mon', 'CEA313', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-6', 'J. Lorico'],
            ['Mon', 'CEA313', '15:00', '18:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-7', 'J. Lorico'],
            ['Mon', 'CEA313', '18:00', '21:00', 'CMPE 402', 'Project Management', 'BSCPE 4-7', 'J. Lorico'],
            ['Mon', 'CEA314', '10:30', '13:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-3', 'A. Racusa'],
            ['Mon', 'CEA314', '13:30', '16:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-1', 'M. Malbog'],
            ['Mon', 'CEA314', '16:30', '21:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-6', 'M. Malbog'],
            ['Mon', 'CEA315', '11:30', '13:30', 'CMPE 453', 'Software Processes', 'BSCPE 4-6', 'A. Delacruz'],
            ['Mon', 'CEA315', '14:30', '16:30', 'CMPE 453', 'Software Processes', 'BSCPE 4-7', 'A. Delacruz'],
            ['Mon', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-5', 'J. Delacruz'],
            ['Mon', 'CEA316', '10:30', '13:30', 'MATH 029', 'Differential Equations', 'BSCPE 2-7', null],
            ['Mon', 'CEA316', '13:30', '16:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-6', 'F. Natividad'],
            ['Mon', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-1', 'J. Avila'],
            ['Mon', 'CEA413', '07:30', '10:30', 'GEED', 'General Education', null, null],
            ['Mon', 'CEA413', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-1', null],
            ['Mon', 'CEA413', '13:30', '16:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-4', null],
            ['Mon', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', null, null],
            ['Mon', 'CEA207', '07:30', '21:00', 'BLOCKED', 'Not available (yellow on timetable, no label)'],

            // ---------------- TUESDAY ----------------
            ['Tue', 'CEA300', '11:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-5', 'O. Pajabera'],
            ['Tue', 'CEA300', '15:00', '18:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-3', 'L. Reyes'],
            ['Tue', 'CEA300', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 1-1P', 'J. Binuya'],
            ['Tue', 'CEA302', '11:00', '14:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-3', 'L. Reyes'],
            ['Tue', 'CEA302', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-6', 'J. Binuya'],
            ['Tue', 'CEA302', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-7', 'R. Fernando'],
            ['Tue', 'CEA310', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera'],
            ['Tue', 'CEA310', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-7', 'J. Binuya'],
            ['Tue', 'CEA310', '13:30', '16:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-4', 'J. Rodriguez'],
            ['Tue', 'CEA310', '18:00', '20:00', 'CMPE 452', 'Digital Image Processing', 'BSCPE 4-1', 'C. Casuat'],
            ['Tue', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay'],
            ['Tue', 'CEA311', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-6', 'R. Mahaguay'],
            ['Tue', 'CEA311', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-2', 'A. Racusa'],
            ['Tue', 'CEA311', '17:00', '20:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-2', 'A. Racusa'],
            ['Tue', 'CEA312', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-1', 'G. Avena'],
            ['Tue', 'CEA312', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-3', 'G. Avena'],
            ['Tue', 'CEA312', '14:30', '16:30', 'CMPE 453', 'Database Implementation', 'BSCPE 4-2', 'A. Delacruz'],
            ['Tue', 'CEA312', '18:00', '21:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-1P', 'O. Pajabera'],
            ['Tue', 'CEA313', '08:00', '09:00', 'CMPE 101', null, 'BSCPE 1-2', 'P. Tenerife'],  // source: "CPE 1-2"
            ['Tue', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'T. Velardo'],
            ['Tue', 'CEA313', '13:30', '16:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-4', 'M. Bautista'],
            ['Tue', 'CEA313', '16:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-4', 'M. Bautista'],
            ['Tue', 'CEA313', '18:00', '21:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-3', 'J. Cansino'],  // source says CMPE 305
            ['Tue', 'CEA314', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-4', 'C. Casuat'],
            ['Tue', 'CEA314', '11:00', '14:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-5', 'J. Lorico'],
            ['Tue', 'CEA314', '14:00', '17:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-5', 'J. Lorico'],
            ['Tue', 'CEA314', '18:00', '21:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-2', 'M. Ello'],
            ['Tue', 'CEA315', '10:30', '13:30', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-8', 'R. Ado'],
            ['Tue', 'CEA315', '14:30', '16:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-3', 'O. Pajabera'],
            ['Tue', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-3', 'J. Delacruz'],
            ['Tue', 'CEA316', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo'],
            ['Tue', 'CEA316', '10:30', '13:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-4', 'F. Natividad'],
            ['Tue', 'CEA316', '13:30', '16:30', 'CMPE 452', 'Digital Image Processing', 'BSCPE 4-1', 'C. Casuat'],
            ['Tue', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-5', 'F. Natividad'],
            ['Tue', 'CEA413', '07:30', '10:30', 'GEED', 'General Education', null, null],
            ['Tue', 'CEA413', '10:30', '13:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-4', 'A. Racusa'],
            ['Tue', 'CEA413', '13:30', '16:30', 'MATH 101', 'Calculus 1', 'BSCPE 1-4', null],
            ['Tue', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', null, null],
            ['Tue', 'CEA207', '07:30', '21:00', 'BLOCKED', 'Not available (yellow on timetable, no label)'],

            // ---------------- WEDNESDAY ----------------
            ['Wed', 'CEA300', '07:30', '10:30', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-1P', 'R. Marasigan'],
            ['Wed', 'CEA300', '10:30', '13:30', 'CMPE 402', 'Project Management', 'BSCPE 4-2', 'J. Lorico'],
            ['Wed', 'CEA300', '15:00', '18:00', 'CMPE 402', 'Project Management', 'BSCPE 4-3', 'L. Reyes'],
            ['Wed', 'CEA300', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-4', 'L. Reyes'],
            ['Wed', 'CEA302', '07:30', '21:00', 'BLOCKED', 'Used by ECE (yellow on timetable)'],
            ['Wed', 'CEA310', '10:00', '12:00', 'CMPE 353', 'Introduction to Big Data', 'BSCPE 3-3', 'M. Malbog'],
            ['Wed', 'CEA310', '12:00', '14:00', 'CMPE 354', 'Enterprise Software', 'BSCPE 3-7', 'J. Tena'],
            ['Wed', 'CEA310', '15:00', '18:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-2', 'J. Avila'],
            ['Wed', 'CEA310', '18:00', '21:00', 'CMPE 309', 'Feedback and Control Systems', 'BSCPE 4-1P', 'J. Delacruz'],
            ['Wed', 'CEA311', '08:00', '09:00', 'CMPE 101', null, 'BSCPE 1-1', 'R. Mahaguay'],  // source: "CPE 1-1"
            ['Wed', 'CEA311', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'P. Tenerife'],
            ['Wed', 'CEA311', '13:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'J. Rodriguez'],
            ['Wed', 'CEA311', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-6', 'M. Malbog'],
            ['Wed', 'CEA312', '07:30', '10:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-7', 'A. Racusa'],
            ['Wed', 'CEA312', '12:00', '15:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-3', 'A. Racusa'],
            ['Wed', 'CEA312', '15:00', '18:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-5', 'M. Bautista'],
            ['Wed', 'CEA312', '18:00', '21:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-1', 'M. Bautista'],
            ['Wed', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'P. Tenerife'],
            ['Wed', 'CEA313', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-1', 'J. Rodriguez'],
            ['Wed', 'CEA313', '13:30', '16:30', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-6', 'K. Tancinco'],
            ['Wed', 'CEA313', '16:30', '18:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-7', 'M. Malbog'],
            ['Wed', 'CEA313', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 3-5', 'J. Binuya'],
            ['Wed', 'CEA314', '07:30', '10:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'L. Lacatan'],
            ['Wed', 'CEA314', '10:30', '13:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-2', 'M. Bautista'],
            ['Wed', 'CEA314', '15:00', '18:00', 'CMPE 351', 'Network Administration', 'BSCPE 3-4', 'J. Binuya'],
            ['Wed', 'CEA314', '18:00', '21:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-3', 'M. Ello'],
            ['Wed', 'CEA315', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-2', 'C. Casuat'],
            ['Wed', 'CEA315', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-2', 'C. Casuat'],
            ['Wed', 'CEA315', '13:30', '16:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-1', 'M. Malbog'],
            ['Wed', 'CEA315', '18:00', '21:00', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-2', 'K. Tancinco'],
            ['Wed', 'CEA316', '11:00', '14:00', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-4', 'F. Natividad'],
            ['Wed', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 1-2P', 'J. Avila'],
            ['Wed', 'CEA413', '12:00', '15:00', 'GEED', 'General Education', null, null],
            ['Wed', 'CEA413', '15:00', '18:00', 'MATH 029', 'Differential Equations', 'BSCPE 2-2', null],
            ['Wed', 'CEA413', '18:00', '21:00', 'CMPE 453', 'Software Processes', 'BSCPE 4-7', 'C. Casuat'],
            ['Wed', 'CEA207', '10:30', '13:30', 'BLOCKED', 'Reserved (light green on timetable, no label)'],
            ['Wed', 'CEA207', '13:30', '16:30', 'ENGL 013', 'Technical Report Writing', 'BSCPE 1-2P', null],
            ['Wed', 'CEA207', '18:00', '21:00', 'MATH 101', 'Calculus 1', 'BSCPE 1-7', null],

            // ---------------- THURSDAY ----------------
            ['Thu', 'CEA300', '07:30', '10:30', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-3P', 'R. Marasigan'],
            ['Thu', 'CEA300', '11:00', '14:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-4', 'J. Binuya'],
            ['Thu', 'CEA300', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-4', 'J. Binuya'],
            ['Thu', 'CEA300', '18:00', '21:00', 'CMPE 351', 'Network Administration', 'BSCPE 1-2P', 'J. Binuya'],
            ['Thu', 'CEA302', '13:30', '16:30', 'ENSC 029', 'Technopreneurship 101', 'BSCE 3-7', 'R. Marasigan'],
            ['Thu', 'CEA302', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-7', 'R. Fernando'],
            ['Thu', 'CEA310', '10:30', '13:30', 'CMPE 302', 'Introduction to HDL', 'BSCPE 1-1P', 'J. Tena'],
            ['Thu', 'CEA310', '15:00', '18:00', 'MATH 029', 'Differential Equations', 'BSCPE 2-5', null],
            ['Thu', 'CEA310', '18:00', '21:00', 'MATH 101', 'Calculus 1', 'BSCPE 1-6', null],
            ['Thu', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay'],
            ['Thu', 'CEA311', '10:30', '15:00', 'CMPE 352', 'Predictive Analytics', 'BSCPE 3-2', 'M. Malbog'],
            ['Thu', 'CEA311', '15:00', '18:00', 'CMPE 011', 'Computer Programming', 'BSREM 1-2', 'M. Bautista'],
            ['Thu', 'CEA311', '18:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSREM 1-2', 'M. Bautista'],
            ['Thu', 'CEA312', '10:30', '13:30', 'CMPE 306', 'Mixed Signals and Sensors', 'BSCPE 3-6', 'M. Bautista'],
            ['Thu', 'CEA312', '15:00', '18:00', 'CMPE 303', 'CpE Drafting and Design', 'BSCPE 3-5', 'A. Racusa'],
            ['Thu', 'CEA312', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-5', 'M. Malbog'],
            ['Thu', 'CEA313', '07:30', '10:30', 'CMPE 011', 'Computer Programming', 'BSREM 1-1', 'A. Racusa'],
            ['Thu', 'CEA313', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSREM 1-1', 'A. Racusa'],
            ['Thu', 'CEA313', '15:00', '18:00', 'CMPE 201', 'Data Structures', 'BSCPE 2-7', 'J. Lorico'],
            ['Thu', 'CEA313', '18:00', '21:00', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-5', 'T. Velardo'],
            ['Thu', 'CEA314', '07:30', '10:30', 'CMPE 402', 'Project Management', 'BSCPE 1-1P', 'L. Lacatan'],
            ['Thu', 'CEA314', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-1', 'L. Lacatan'],
            ['Thu', 'CEA314', '15:00', '18:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-7', 'J. Rodriguez'],
            ['Thu', 'CEA314', '18:00', '21:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-7', 'J. Rodriguez'],
            ['Thu', 'CEA315', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-6', 'O. Pajabera'],
            ['Thu', 'CEA315', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-6', 'J. Lorico'],
            ['Thu', 'CEA315', '13:30', '16:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-5', 'O. Pajabera'],
            ['Thu', 'CEA315', '16:30', '18:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-6', 'M. Malbog'],
            ['Thu', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-5', 'J. Delacruz'],
            ['Thu', 'CEA316', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-7', null],
            ['Thu', 'CEA316', '13:30', '16:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-6', 'F. Natividad'],
            ['Thu', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-5', 'F. Natividad'],
            ['Thu', 'CEA413', '16:30', '19:30', 'GEED', 'General Education', null, null],
            ['Thu', 'CEA207', '07:30', '21:00', 'BLOCKED', 'Not available (yellow on timetable, no label)'],

            // ---------------- FRIDAY ----------------
            ['Fri', 'CEA300', '07:30', '10:30', 'ENSC 029', 'Technopreneurship 101', 'BSEE 4-4', 'F. Oquindo'],
            ['Fri', 'CEA300', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-7', 'J. Binuya'],
            ['Fri', 'CEA300', '15:00', '18:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-6', 'J. Binuya'],
            ['Fri', 'CEA300', '18:00', '20:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-1P', 'O. Pajabera'],
            ['Fri', 'CEA302', '07:30', '21:00', 'BLOCKED', 'Used by ECE (yellow on timetable)'],
            ['Fri', 'CEA310', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-1', 'G. Avena'],
            ['Fri', 'CEA310', '10:30', '13:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-3', 'G. Avena'],
            ['Fri', 'CEA310', '15:00', '18:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-7', 'L. Reyes'],
            ['Fri', 'CEA310', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-7', 'L. Reyes'],
            ['Fri', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay'],
            ['Fri', 'CEA311', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-6', 'R. Mahaguay'],
            ['Fri', 'CEA311', '15:00', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'M. Bautista'],
            ['Fri', 'CEA311', '18:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSME 2-3', 'M. Bautista'],
            ['Fri', 'CEA312', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera'],
            ['Fri', 'CEA312', '12:00', '15:00', 'CMPE 353', 'Introduction to Big Data', 'BSCPE 3-3', 'M. Malbog'],
            ['Fri', 'CEA312', '15:00', '18:00', 'CMPE 310', 'Computer Networks', 'BSCPE 1-1P', 'J. Avila'],
            ['Fri', 'CEA312', '18:00', '21:00', 'CMPE 310', 'Computer Networks', 'BSCPE 1-1P', 'J. Avila'],
            ['Fri', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'P. Tenerife'],
            ['Fri', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'P. Tenerife'],
            ['Fri', 'CEA313', '13:30', '18:00', 'CMPE 011', 'Computer Programming', 'BSME 2-1', 'J. Tena'],
            ['Fri', 'CEA313', '18:00', '21:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-2', 'K. Tancinco'],
            ['Fri', 'CEA314', '07:30', '10:30', 'CMPE 201', 'Data Structures', 'BSCPE 2-4', 'C. Casuat'],
            ['Fri', 'CEA314', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'T. Velardo'],
            ['Fri', 'CEA314', '13:30', '16:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-4', 'J. Rodriguez'],
            ['Fri', 'CEA314', '16:30', '21:00', 'CMPE 011', 'Programming Logic and Design', 'BSCPE 1-7', 'M. Malbog'],
            ['Fri', 'CEA315', '07:30', '10:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-3', 'J. Cansino'],
            ['Fri', 'CEA315', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-1', 'J. Cansino'],
            ['Fri', 'CEA315', '13:30', '16:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-5', 'K. Tancinco'],
            ['Fri', 'CEA315', '18:00', '21:00', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-3', 'J. Delacruz'],
            ['Fri', 'CEA316', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo'],
            ['Fri', 'CEA316', '10:30', '13:30', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-4', 'F. Natividad'],
            ['Fri', 'CEA316', '15:00', '18:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-3', 'F. Natividad'],
            ['Fri', 'CEA316', '18:00', '21:00', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-3', 'F. Natividad'],
            ['Fri', 'CEA413', '07:30', '10:30', 'BLOCKED', 'Reserved (light green on timetable, no label)'],
            ['Fri', 'CEA413', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-5', null],
            ['Fri', 'CEA413', '13:30', '16:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-1', null],
            ['Fri', 'CEA413', '18:00', '21:00', 'GEED', 'General Education', null, null],
            ['Fri', 'CEA207', '07:30', '21:00', 'BLOCKED', 'Not available (yellow on timetable, no label)'],

            // ---------------- SATURDAY ----------------
            ['Sat', 'CEA300', '07:30', '10:30', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-7', 'P. Tenerife'],
            ['Sat', 'CEA300', '10:30', '13:30', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-7', 'P. Tenerife'],
            ['Sat', 'CEA300', '14:00', '17:00', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'O. Pajabera'],
            ['Sat', 'CEA300', '18:00', '21:00', 'CMPE 40223', 'Trends in Software Development', 'BSCPE 4-1P', 'M. Legarda'],
            ['Sat', 'CEA302', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-4', 'T. Velardo'],
            ['Sat', 'CEA302', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-4', 'T. Velardo'],
            ['Sat', 'CEA302', '13:30', '16:30', 'CMPE 40223', 'Trends in Software Development', 'BSCPE 4-3P', 'M. Legarda'],
            ['Sat', 'CEA302', '18:00', '21:00', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-3P', 'R. Marasigan'],
            ['Sat', 'CEA310', '07:30', '10:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'L. Lacatan'],
            ['Sat', 'CEA310', '10:30', '13:30', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-1', 'L. Lacatan'],
            ['Sat', 'CEA310', '14:00', '17:00', 'CMPE 402', 'Project Management', 'BSCPE 1-2P', 'L. Lacatan'],
            ['Sat', 'CEA310', '17:00', '20:00', 'CMPE 402', 'Project Management', 'BSCPE 4-5', 'L. Lacatan'],
            ['Sat', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-1', 'R. Mahaguay'],
            ['Sat', 'CEA311', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-6', 'R. Mahaguay'],
            ['Sat', 'CEA311', '14:00', '17:00', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-6', 'R. Ado'],
            ['Sat', 'CEA311', '17:00', '18:00', 'CMPE 101', null, 'BSCPE 1-3', 'M. Legarda'],  // source: "CPE 1-3"
            ['Sat', 'CEA311', '18:00', '21:00', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-4', 'O. Pajabera'],
            ['Sat', 'CEA312', '07:30', '10:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-3', 'R. Marasigan'],
            ['Sat', 'CEA312', '10:30', '13:30', 'CMPE 011', 'Computer Programming', 'BSIE 1-3', 'R. Marasigan'],
            ['Sat', 'CEA312', '14:00', '17:00', 'CMPE 402', 'Project Management', 'BSCPE 4-2', 'P. Tenerife'],
            ['Sat', 'CEA312', '17:00', '21:00', 'CMPE 011', 'Computer Programming', 'BSIE 1-5', 'J. Tena'],
            ['Sat', 'CEA313', '07:30', '10:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-4', 'J. Cansino'],
            ['Sat', 'CEA313', '10:30', '13:30', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-4', 'J. Cansino'],
            ['Sat', 'CEA313', '14:00', '20:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-3', 'J. Cansino'],
            ['Sat', 'CEA314', '07:30', '12:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-5', 'F. Oquindo'],
            ['Sat', 'CEA314', '12:30', '13:30', 'CMPE 101', null, 'BSCPE 1-7', 'F. Oquindo'],  // source: "CPE 1-7"
            ['Sat', 'CEA314', '14:00', '15:00', 'CMPE 101', null, 'BSCPE 1-6', 'F. Oquindo'],  // source: "CPE 1-6"
            ['Sat', 'CEA314', '15:00', '16:00', 'CMPE 101', null, 'BSCPE 1-4', 'F. Oquindo'],  // source: "CPE 1-4"
            ['Sat', 'CEA314', '16:30', '21:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-6', 'F. Oquindo'],
            ['Sat', 'CEA315', '07:30', '10:30', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-6', 'O. Pajabera'],
            ['Sat', 'CEA315', '10:30', '13:30', 'CMPE 451', 'Introduction to Cybersecurity', 'BSCPE 4-3', 'O. Pajabera'],
            ['Sat', 'CEA315', '14:00', '17:00', 'CMPE 30274', 'Embedded Systems', 'BSCOE 4-1P', 'R. Marasigan'],
            ['Sat', 'CEA315', '17:00', '21:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'D. Madrigalejos'],  // source title: "Database Systems"
            ['Sat', 'CEA316', '07:30', '10:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-5', 'D. Madrigalejos'],
            ['Sat', 'CEA316', '10:30', '13:30', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-5', 'D. Madrigalejos'],
            ['Sat', 'CEA316', '15:00', '18:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-3', 'J. Avila'],
            ['Sat', 'CEA316', '18:00', '21:00', 'CMPE 302', 'Introduction to HDL', 'BSCPE 3-7', 'J. Avila'],
            ['Sat', 'CEA413', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-3', 'M. Ello'],
            ['Sat', 'CEA413', '10:30', '13:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-2', 'M. Ello'],
            ['Sat', 'CEA413', '15:00', '17:00', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'D. Madrigalejos'],  // source title: "Database Systems"
            ['Sat', 'CEA413', '17:00', '20:00', 'CHEM', 'Chemistry for Engineers', null, 'F. Lacsa'],
            ['Sat', 'CEA207', '10:30', '13:30', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-3', null],
            ['Sat', 'CEA207', '14:00', '17:00', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-5', 'T. Velardo'],
            ['Sat', 'CEA207', '18:00', '21:00', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-6', null],
        ];
    }

    protected function equipment(): array
    {
        // [category => [item => how many the department owns]], in the request form's order.
        // PLACEHOLDER STOCK: every item starts at 10. Replace with the real counts.
        return [
            'Audiovisual Equipment' => [
                'Projector' => 10,
                'Projector Power Cord' => 10,
                'TV Remote' => 10,
                'TV Power Cord' => 10,
                'Extension Cord' => 10,
                'HDMI Cable' => 10,
                'USB Cable (Type A to Type B)' => 10,
            ],
            'Classroom Essentials' => [
                'Armed Chair' => 10,
                'Monoblock Chair' => 10,
                'Eraser' => 10,
                'Whiteboard Marker' => 10,
                'Electric Fan' => 10,
                'Scientific Calculator' => 10,
            ],
            'Electrical & Electronic Equipment' => [
                'Analog Multi-Tester' => 10,
                'Digital Multi-Tester' => 10,
                'Power Supply' => 10,
                'Alligator Clip Wire' => 10,
                'FPGA Kit' => 10,
                'Analog Oscilloscope' => 10,
                'Digital Oscilloscope' => 10,
                'Oscilloscope Probe' => 10,
                'Soldering Iron' => 10,
                'Soldering Iron Holder' => 10,
            ],
            'Hardware Equipment' => [
                'System Unit' => 10,
                'Monitor' => 10,
                'Automatic Voltage Regulator (AVR)' => 10,
                'Power Cord' => 10,
                'VGA Cable' => 10,
                'Keyboard' => 10,
                'Mouse' => 10,
                'Laptop' => 10,
                'Laptop Charger' => 10,
                'Screwdriver Set' => 10,
                'Phillips Screwdriver' => 10,
                'Flathead Screwdriver' => 10,
                'Pliers' => 10,
                'Outlet Adapter' => 10,
            ],
            'Network Equipment' => [
                'LAN Cable' => 10,
                'RJ45 Connector' => 10,
                'Wire Stripper' => 10,
                'Crimping Tool' => 10,
                'LAN Tester' => 10,
                'Access Point' => 10,
                'Network Switch' => 10,
            ],
            'Breadboarding Components' => [
                '7-Segment Display' => 10,
                '9 V Battery' => 10,
                'AA Battery' => 10,
                'AAA Battery' => 10,
                'Breadboard' => 10,
                'Button' => 10,
                'Capacitor' => 10,
                'Connecting Wire' => 10,
                'Diode' => 10,
                'DIP Switch' => 10,
                'Integrated Circuit (IC)' => 10,
                'Jump Wire' => 10,
                'LED' => 10,
                'Potentiometer' => 10,
                'Relay' => 10,
                'Resistor' => 10,
                'Solid Wire' => 10,
                'Switch' => 10,
                'Transistor' => 10,
            ],
        ];
    }
}
