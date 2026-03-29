<?php

return [
    'business' => [
        'business_type' => 'hotel',
        'business_name' => 'AI Demo Grand Hotel',
        'business_address' => '45 Royal Avenue',
        'business_postcode' => 'W1A 4AA',
        'business_EmailAddress' => 'contact@aigrandhotel.com',
        'business_PhoneNumber' => '+441234889900',
        'business_About' => 'A premium city hotel focused on guest comfort, cleanliness, and smart feedback collection',
        'business_GoogleMapApi' => '',
        'business_homeText' => 'Welcome to AI Demo Grand Hotel',
        'business_AdditionalInformation' => '24/7 reception · Free WiFi · Daily housekeeping',
        'business_Webpage' => 'https://aigrandhotel.com',
        'header_image' => '/header_image/hotel.webp',
        'rating_page_image' => '/rating_page_image/hotel.webp',
        'placeholder_image' => '/placeholder_image/room.webp',
        'primary_color' => '#1f2a44',
        'secondary_color' => '#c9a24d',
        'client_primary_color' => '#1f2a44',
        'client_secondary_color' => '#c9a24d',
        'client_tertiary_color' => '#ffffff',
        'user_review_report' => true,
        'review_type' => 'star',
        'is_branch' => true,
        'is_review_slider' => false,
        'review_only' => false,
        'service_plan_id' => 3,
    ],

    'branches' => [
        ['name' => 'Central London Hotel', 'city' => 'London', 'postcode' => 'W1A 4AA'],
        ['name' => 'Canary Wharf Hotel', 'city' => 'London', 'postcode' => 'E14 5HQ'],
        ['name' => 'Manchester City Hotel', 'city' => 'Manchester', 'postcode' => 'M1 2AB'],
        ['name' => 'Birmingham Grand Hotel', 'city' => 'Birmingham', 'postcode' => 'B2 4QA'],
    ],

    'names' => [
        'staff' => [
            'first' => ['Daniel', 'Sophia', 'Oliver', 'Emily', 'James', 'Amelia', 'Henry', 'Charlotte'],
            'last' => ['Walker', 'Thompson', 'Evans', 'Roberts', 'Hall', 'Turner', 'Parker', 'Collins'],
        ],
        'customers' => [
            'first' => ['John', 'Anna', 'Michael', 'Laura', 'David', 'Sara', 'Chris', 'Nina'],
            'last' => ['Smith', 'Brown', 'Wilson', 'Taylor', 'Anderson', 'White', 'Martin', 'Clark'],
        ],
    ],

    'services' => [
        [
            'name' => 'Standard Rooms',
            'description' => 'Comfortable rooms for solo and business travellers',
            'areas' => ['Room 101', 'Room 102', 'Room 103']
        ],
        [
            'name' => 'Deluxe Rooms',
            'description' => 'Spacious rooms with premium amenities',
            'areas' => ['Room 201', 'Room 202', 'Room 203']
        ],
        [
            'name' => 'Executive Rooms',
            'description' => 'Executive stay with workspace and lounge access',
            'areas' => ['Room 301', 'Room 302', 'Room 303']
        ],
        [
            'name' => 'Family Rooms',
            'description' => 'Large rooms suitable for families',
            'areas' => ['Room 401', 'Room 402', 'Room 403']
        ],
        [
            'name' => 'Hotel Suites',
            'description' => 'Luxury suites with living area and city view',
            'areas' => ['Suite 501', 'Suite 502', 'Suite 503']
        ],
    ],

    'surveys' => [
        'specific_names' => [
            'Standard Room Survey',
            'Deluxe Room Survey',
            'Executive Room Survey',
            'Family Room Survey',
            'Suite Stay Survey',
        ],
    ],

    'questions' => [
        'overall' => [
            'How would you rate your overall stay at our hotel?',
            'How satisfied were you with your room cleanliness?',
            'How comfortable was your room?',
            'How professional was the hotel staff?',
            'How safe did you feel during your stay?',
            'How likely are you to stay with us again?',
            'How would you rate value for money?',
            'How satisfied were you with hotel facilities?',
        ],
        'survey_specific' => [
            // Room-specific (used after room selection)
            'How clean was your room?',
            'How comfortable was the bed?',
            'How quiet was your room during the night?',
            'How satisfied were you with room temperature?',
            'How well did housekeeping meet expectations?',
            'How would you rate the bathroom cleanliness?',

            'How satisfied were you with room size?',
            'How would you rate in-room amenities?',
            'How comfortable was the seating area?',
            'How was the lighting in your room?',
            'How satisfied were you with room privacy?',
            'How would you rate your overall room experience?',
        ],
    ],

    'tags' => [
        'default' => [
            'Excellent',
            'Outstanding',
            'Very Comfortable',
            'Clean',
            'Quiet',
            'Average',
            'Needs Improvement',
            'Uncomfortable',
            'Noisy',
            'Poor',
        ],
    ],

    'star_tag_mappings' => [
        1 => ['Poor', 'Uncomfortable'],
        2 => ['Needs Improvement', 'Noisy'],
        3 => ['Average'],
        4 => ['Clean', 'Quiet'],
        5 => ['Excellent', 'Outstanding', 'Very Comfortable'],
    ],

    'review_templates' => [
        ['rating' => 5, 'comment' => 'Fantastic hotel stay. Room was spotless, quiet, and very comfortable. Highly recommended.'],
        ['rating' => 4, 'comment' => 'Very good experience. Comfortable room and friendly staff. Would stay again.'],
        ['rating' => 3, 'comment' => 'Average stay. Room was okay but could be improved in some areas.'],
        ['rating' => 2, 'comment' => 'Below expectations. Room cleanliness and noise levels were disappointing.'],
        ['rating' => 1, 'comment' => 'Terrible experience. Room was uncomfortable and poorly maintained.'],
    ],

    'question_categories' => [
        'Staff' => [
            'Staff Professionalism',
            'Staff Responsiveness',
        ],
        'Room Quality' => [
            'Cleanliness',
            'Comfort',
            'Noise',
        ],
        'Facilities' => [
            'Amenities',
            'Bathroom',
            'Temperature',
        ],
        'Experience' => [
            'Overall Satisfaction',
            'Value for Money',
        ],
    ],

    'question_category_mappings' => [
        'How would you rate your overall stay at our hotel?' => 'Overall Satisfaction',
        'How satisfied were you with your room cleanliness?' => 'Cleanliness',
        'How comfortable was your room?' => 'Comfort',
        'How professional was the hotel staff?' => 'Staff Professionalism',
        'How safe did you feel during your stay?' => 'Overall Satisfaction',
        'How likely are you to stay with us again?' => 'Overall Satisfaction',
        'How would you rate value for money?' => 'Value for Money',
        'How satisfied were you with hotel facilities?' => 'Amenities',

        'How clean was your room?' => 'Cleanliness',
        'How comfortable was the bed?' => 'Comfort',
        'How quiet was your room during the night?' => 'Noise',
        'How satisfied were you with room temperature?' => 'Temperature',
        'How well did housekeeping meet expectations?' => 'Staff Responsiveness',
        'How would you rate the bathroom cleanliness?' => 'Bathroom',
        'How satisfied were you with room size?' => 'Comfort',
        'How would you rate in-room amenities?' => 'Amenities',
        'How comfortable was the seating area?' => 'Comfort',
        'How was the lighting in your room?' => 'Amenities',
        'How satisfied were you with room privacy?' => 'Overall Satisfaction',
        'How would you rate your overall room experience?' => 'Overall Satisfaction',
    ],
];
