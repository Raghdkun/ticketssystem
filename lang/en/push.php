<?php

/*
 * Push message bodies.
 *
 * Every kind holds several variants rather than one fixed string, and the
 * sender rotates through them: the same person books repeatedly, and a
 * notification that arrives word-for-word identical every time reads as
 * machinery rather than a venue talking to them. The subject never moves --
 * only the wording.
 */
return [
    'status' => [
        'pending' => [
            'Seats held. Pay at the venue to confirm.',
            'Your seats are reserved. Settle up at the door and you are set.',
            'Booked, not paid yet. Bring the fare to the venue.',
            'Holding your place. Payment happens in person.',
            'Reservation received. Pay at the venue to lock it in.',
            'Seats are yours for now. Confirm by paying at the door.',
        ],
        'paid' => [
            'Confirmed. See you there.',
            'Paid and confirmed. Your seats are waiting.',
            'All set. Your ticket is confirmed.',
            'Payment received. Enjoy the show.',
            'Confirmed at the door. Nothing else to do.',
            'You are in. Ticket confirmed.',
        ],
        'cancelled' => [
            'Your reservation has been cancelled.',
            'This booking was cancelled and the seats released.',
            'Cancelled. The seats have gone back on sale.',
            'That reservation is no longer active.',
            'Booking cancelled. No payment is due.',
            'Your seats have been released.',
        ],
        'expired' => [
            'The hold ran out and the seats were released.',
            'Time was up, so your seats went back on sale.',
            'Your reservation expired before it was paid.',
            'The hold window closed and the seats are free again.',
            'Not paid in time. The seats have been released.',
            'That hold has lapsed. Book again if you still want in.',
        ],
        'no_show' => [
            'Marked as a no-show at the door.',
            'The venue recorded this ticket as unused.',
            'You were not checked in, so this is closed as a no-show.',
            'This ticket went unused on the night.',
            'Recorded as a no-show by the venue.',
            'Closed as unused after the doors shut.',
        ],
    ],

    /*
     * The hold is about to lapse. Urgent but friendly -- most people who let a
     * hold expire simply forgot, not changed their mind.
     */
    'reminder' => [
        'Your seats are held until :time. Pay at the venue to keep them.',
        'Still holding your seats. They are released at :time.',
        'A reminder: pay at the venue before :time or the seats go back.',
        'Your hold ends at :time. Drop by the venue to confirm.',
        'Do not lose your seats. Payment is due by :time.',
        'Time is running down on your hold. It ends at :time.',
    ],

    // A seat came back on a sold-out event and somebody is waiting for it.
    'seat_freed' => [
        'A seat just opened up for :event. Book before it goes.',
        'Good news: :event has space again.',
        'Somebody released their seats for :event. Yours if you are quick.',
        ':event is no longer sold out. Grab a seat.',
        'Space has come back on :event. First come, first served.',
        'A place has freed up for :event.',
    ],
];
