<?php

namespace Tests\Unit;

use App\Support\AccountRules;
use PHPUnit\Framework\TestCase;

class AccountRulesTest extends TestCase
{
    public function test_emails_are_saved_without_spaces_or_capitals(): void
    {
        $this->assertSame('bea.prieto@gmail.com', AccountRules::email('  Bea.Prieto@GMAIL.com '));
        $this->assertSame('', AccountRules::email(null));
    }

    public function test_reps_can_use_gmail_or_pup_webmail_only(): void
    {
        $this->assertTrue(AccountRules::isRepEmail('bea@gmail.com'));
        $this->assertTrue(AccountRules::isRepEmail('BEA@Gmail.Com'));
        $this->assertTrue(AccountRules::isRepEmail('beaprieto@iskolarngbayan.pup.edu.ph'));

        $this->assertFalse(AccountRules::isRepEmail('bea@yahoo.com'));
        $this->assertFalse(AccountRules::isRepEmail('bea@pup.edu.ph'));                  // faculty mail, not student webmail
        $this->assertFalse(AccountRules::isRepEmail('bea@notgmail.com'));
        $this->assertFalse(AccountRules::isRepEmail('bea@gmail.com.evil.test'));
        $this->assertFalse(AccountRules::isRepEmail('gmail.com'));
        $this->assertFalse(AccountRules::isRepEmail('@gmail.com'));
    }

    public function test_school_ids_are_tidied_up(): void
    {
        $this->assertSame('2021-00123-MN-0', AccountRules::schoolId('2021-00123-MN-0'));
        $this->assertSame('2021-00123-MN-0', AccountRules::schoolId(' 2021-00123-mn-0 '));
        $this->assertSame('2021-00123-MN-0', AccountRules::schoolId('202100123MN0'));
        $this->assertSame('2021-00123-MN-0', AccountRules::schoolId('2021 00123 MN 0'));
        $this->assertSame('', AccountRules::schoolId(null));
        $this->assertSame('12345', AccountRules::schoolId('12345'));       // left as typed, so the check can say it's wrong
    }

    public function test_school_ids_must_look_like_pup_student_numbers(): void
    {
        $this->assertTrue(AccountRules::isSchoolId('2021-00123-MN-0'));
        $this->assertTrue(AccountRules::isSchoolId('2019-12345-QC-1'));

        $this->assertFalse(AccountRules::isSchoolId('2021-0123-MN-0'));      // 4 digits in the middle
        $this->assertFalse(AccountRules::isSchoolId('2021-00123-M-0'));
        $this->assertFalse(AccountRules::isSchoolId('2021-00123-mn-0'));     // tidy it first
        $this->assertFalse(AccountRules::isSchoolId('2021-00123-MN-0 '));
        $this->assertFalse(AccountRules::isSchoolId(''));
    }

    public function test_initials_for_people_without_a_photo(): void
    {
        $this->assertSame('BP', AccountRules::initials('Bea Prieto'));
        $this->assertSame('JC', AccountRules::initials('Juan  Dela Cruz'));
        $this->assertSame('C', AccountRules::initials('cher'));
        $this->assertSame('ÑO', AccountRules::initials('ñino ocampo'));
        $this->assertSame('?', AccountRules::initials('   '));
    }
}
