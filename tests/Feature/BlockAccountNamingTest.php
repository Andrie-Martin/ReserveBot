<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Section;
use App\Models\User;
use Tests\TestCase;

/**
 * Sandbox issue 6: a 'rep' account is a block's account. It goes by its block, the block's representative is the
 * name on the account, and a booking keeps the representative it was made under. Needs no database.
 */
class BlockAccountNamingTest extends TestCase
{
    private function block(string $representative = 'Bea Prieto', ?string $blockName = 'BSCPE 4-5'): User
    {
        $account = new User(['name' => $representative, 'email' => 'block@gmail.com', 'role' => 'rep']);
        $account->setRelation('section', $blockName ? new Section(['name' => $blockName, 'program' => 'BSCPE']) : null);

        return $account;
    }

    private function person(string $role, string $name): User
    {
        $account = new User(['name' => $name, 'email' => 'person@gmail.com', 'role' => $role]);
        $account->setRelation('section', null);

        return $account;
    }

    public function test_the_role_is_called_block_account(): void
    {
        $this->assertSame('Block account', User::ROLE_LABELS['rep']);
        $this->assertSame('Block account', $this->block()->roleLabel());
        $this->assertTrue($this->block()->isBlock());
        $this->assertFalse($this->person('lab_staff', 'Lab Assistant')->isBlock());
        $this->assertFalse($this->person('admin', 'Office Admin')->isBlock());
    }

    public function test_a_block_account_goes_by_its_block_and_names_its_representative(): void
    {
        $account = $this->block('Bea Prieto', 'BSCPE 4-5');
        $this->assertSame('BSCPE 4-5', $account->displayName());
        $this->assertSame('Bea Prieto', $account->representativeName());

        // The representative changes; the account is still the block's
        $account->name = 'Carlo Reyes';
        $this->assertSame('BSCPE 4-5', $account->displayName());
        $this->assertSame('Carlo Reyes', $account->representativeName());
    }

    public function test_lab_staff_and_admins_go_by_their_own_name_and_have_no_representative(): void
    {
        foreach (['lab_staff' => 'Lab Assistant', 'admin' => 'Office Admin'] as $role => $name) {
            $account = $this->person($role, $name);
            $this->assertSame($name, $account->displayName());
            $this->assertNull($account->representativeName());
        }
    }

    public function test_a_block_account_without_a_block_yet_falls_back_to_the_representatives_name(): void
    {
        $this->assertSame('Bea Prieto', $this->block('Bea Prieto', null)->displayName());
    }

    public function test_a_booking_keeps_the_representative_it_was_made_under(): void
    {
        $account = $this->block('Bea Prieto');
        $booking = new Reservation(['rep_name' => 'Bea Prieto']);
        $booking->setRelation('user', $account);
        $this->assertSame('BSCPE 4-5 (Rep: Bea Prieto)', $booking->bookedBy());

        $account->name = 'Carlo Reyes';      // a new representative takes over
        $this->assertSame('Bea Prieto', $booking->representativeName(), 'the old booking still names Bea');
        $this->assertSame('BSCPE 4-5 (Rep: Bea Prieto)', $booking->bookedBy());

        $newer = new Reservation(['rep_name' => 'Carlo Reyes']);
        $newer->setRelation('user', $account);
        $this->assertSame('BSCPE 4-5 (Rep: Carlo Reyes)', $newer->bookedBy());
    }

    public function test_a_booking_from_before_the_column_existed_uses_the_current_representative(): void
    {
        $booking = new Reservation;
        $booking->setRelation('user', $this->block('Bea Prieto'));
        $this->assertSame('Bea Prieto', $booking->representativeName());
        $this->assertSame('BSCPE 4-5 (Rep: Bea Prieto)', $booking->bookedBy());
    }
}
