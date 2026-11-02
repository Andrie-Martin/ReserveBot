<?php

namespace App\Support;

use RuntimeException;

/** Phase 11: a file that can't be read as a timetable. The message is written for the person who uploaded it. */
class SpreadsheetProblem extends RuntimeException {}
