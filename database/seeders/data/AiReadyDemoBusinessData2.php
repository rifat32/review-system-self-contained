<?php

return [
    'business' => [
        'business_name' => 'AI Demo Restaurant & Cafe',
        'business_address' => '123 Main Street, Downtown',
        'business_postcode' => 'SW1A 1AA',
        'business_EmailAddress' => 'contact@aidemo.com',
        'business_PhoneNumber' => '+441234567890',
        'business_About' => 'A modern restaurant chain focused on exceptional customer service',
        'business_GoogleMapApi' => '',
        'business_homeText' => 'Welcome to AI Demo Restaurant',
        'business_AdditionalInformation' => 'Open 7 days a week',
        'business_Webpage' => 'https://aidemo.com',
        'header_image' => '/header_image/default.webp',
        'rating_page_image' => '/rating_page_image/default.webp',
        'placeholder_image' => '/placeholder_image/default.webp',
        'primary_color' => '#172c41',
        'secondary_color' => '#ac8538',
        'client_primary_color' => '#172c41',
        'client_secondary_color' => '#ac8538',
        'client_tertiary_color' => '#ffffff',
        'user_review_report' => true,
        'review_type' => 'star',
        'is_branch' => true,
        'is_review_slider' => false,
        'review_only' => false,
        'service_plan_id' => 3,
    ],

    'branches' => [
        ['name' => 'Downtown Branch', 'city' => 'London', 'postcode' => 'SW1A 1AA'],
        ['name' => 'Westside Branch', 'city' => 'Manchester', 'postcode' => 'M1 1AA'],
        ['name' => 'Eastside Branch', 'city' => 'Birmingham', 'postcode' => 'B1 1AA'],
        ['name' => 'Northside Branch', 'city' => 'Leeds', 'postcode' => 'LS1 1AA'],
    ],

    'names' => [
        'staff' => [
            'first' => ['Amitabh', 'Sarah', 'Michael', 'Emma', 'David', 'Lisa', 'James', 'Anna', 'Robert', 'Maria', 'Tom', 'Sophie'],
            'last' => ['Bachchan', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Anderson', 'Taylor'],
        ],
        'customers' => [
            'first' => ['John', 'Jane', 'Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Henry', 'Isla', 'Jack', 'Kate', 'Leo', 'Mia', 'Noah', 'Olivia', 'Peter', 'Quinn', 'Ruby'],
            'last' => ['Smith', 'Jones', 'Williams', 'Brown', 'Wilson', 'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris', 'Martin', 'Thompson', 'Garcia', 'Martinez', 'Robinson', 'Clark', 'Rodriguez', 'Lewis'],
        ],
    ],

    'services' => [
        [
            'name' => 'Dine-in Restaurant',
            'description' => 'Full-service restaurant dining experience',
            'areas' => ['Main Dining Hall', 'Private Booth', 'Outdoor Patio']
        ],
        [
            'name' => 'Takeaway Service',
            'description' => 'Food to-go with quick pickup',
            'areas' => ['Counter Pickup', 'Drive-thru', 'Curbside Pickup']
        ],
        [
            'name' => 'Delivery Service',
            'description' => 'Home and office delivery',
            'areas' => ['Local Delivery', 'Express Delivery', 'Corporate Delivery']
        ],
        [
            'name' => 'Catering Service',
            'description' => 'Event and party catering',
            'areas' => ['Wedding Catering', 'Corporate Events', 'Private Parties']
        ],
        [
            'name' => 'Coffee Shop',
            'description' => 'Coffee and light snacks',
            'areas' => ['Coffee Bar', 'Seating Area', 'Outdoor Terrace']
        ]
    ],

    'surveys' => [
        'specific_names' => [
            'Dine-in Restaurant Survey',
            'Takeaway Service Survey',
            'Delivery Service Survey',
            'Catering Service Survey',
            'Coffee Shop Survey',
        ],
    ],

    'questions' => [
        'overall' => [
            'How would you rate your overall experience?',
            'Would you recommend us to friends and family?',
            'How likely are you to return?',
            'How satisfied are you with your visit today?',
            'How do you rate our establishment compared to competitors?',
            'How professional was our service?',
            'How welcoming was our staff?',
            'How well did we meet your expectations?',
            'How would you rate the overall quality?',
            'How satisfied are you with your overall experience?',
        ],
        'survey_specific' => [
            // Dine-in Restaurant
            'How would you rate the taste of your meal?',
            'How would you rate food presentation?',
            'How fresh were the ingredients?',
            'How appropriate were the portion sizes?',
            'How would you rate the menu variety?',
            'How was the temperature of your food?',

            // Takeaway Service
            'How friendly was the pickup staff?',
            'How quick was the order preparation?',
            'How well was your order packaged?',
            'How accurate was your takeaway order?',
            'How satisfied were you with pickup instructions?',
            'How would you rate the takeaway experience?',

            // Delivery Service
            'How timely was the delivery?',
            'How well-packaged was your order?',
            'How accurate was your order?',
            'How satisfied were you with the delivery experience?',
            'How professional was the delivery driver?',
            'How would you rate the food temperature upon arrival?',

            // Catering Service
            'How satisfied were you with catering setup?',
            'How would you rate food variety for catering?',
            'How professional was the catering staff?',
            'How well did we accommodate dietary requests?',
            'How would you rate the catering presentation?',
            'How satisfied were you with catering timing?',

            // Coffee Shop
            'How would you rate coffee quality?',
            'How friendly was the barista?',
            'How quick was the service?',
            'How would you rate pastry freshness?',
            'How comfortable was the seating area?',
            'How satisfied were you with the atmosphere?',
        ],
    ],

    'tags' => [
        'default' => [
            'Excellent',
            'Outstanding',
            'Amazing',
            'Good',
            'Great',
            'Pleasant',
            'Average',
            'Fair',
            'Okay',
            'Poor',
            'Below Average',
            'Disappointing',
            'Terrible',
            'Unacceptable'
        ],
    ],

    'star_tag_mappings' => [
        1 => ['Terrible', 'Unacceptable', 'Poor'],
        2 => ['Poor', 'Below Average', 'Disappointing'],
        3 => ['Average', 'Fair', 'Okay'],
        4 => ['Good', 'Great', 'Pleasant'],
        5 => ['Excellent', 'Outstanding', 'Amazing'],
    ],

    'review_templates' => [
        // Positive (5 stars)
        ['rating' => 5, 'comment' => 'Absolutely outstanding experience! The food was exceptional, staff were incredibly attentive, and the atmosphere was perfect. Highly recommend!'],
        ['rating' => 5, 'comment' => 'One of the best dining experiences I\'ve had. Everything from service to food quality was top-notch. Will definitely be back!'],
        ['rating' => 5, 'comment' => 'Fantastic! Fresh ingredients, skillful preparation, and wonderful presentation. The staff went above and beyond.'],
        ['rating' => 5, 'comment' => 'Exceeded all expectations. Great value for money, beautiful ambiance, and delicious food. Five stars all the way!'],

        // Good (4 stars)
        ['rating' => 4, 'comment' => 'Very good overall. Food was tasty and service was friendly. Just a bit of a wait for our order but worth it.'],
        ['rating' => 4, 'comment' => 'Really enjoyed our meal. Staff were helpful and the atmosphere was nice. Would recommend.'],
        ['rating' => 4, 'comment' => 'Great food quality and good portion sizes. Service could be slightly faster but otherwise excellent.'],
        ['rating' => 4, 'comment' => 'Solid experience. Everything was good, though the dessert menu could be more varied.'],

        // Average (3 stars)
        ['rating' => 3, 'comment' => 'Decent meal, nothing spectacular. Service was okay but took a while to get attention.'],
        ['rating' => 3, 'comment' => 'Average experience. Food was fine but not memorable. Prices are a bit high for what you get.'],
        ['rating' => 3, 'comment' => 'It was alright. Some dishes were better than others. Staff seemed a bit overwhelmed.'],
        ['rating' => 3, 'comment' => 'Middle of the road. Good points and bad points balanced out to an okay experience.'],

        // Below Average (2 stars)
        ['rating' => 2, 'comment' => 'Disappointed with our visit. Food was bland and service was slow. Expected better based on reviews.'],
        ['rating' => 2, 'comment' => 'Not a great experience. Long wait times and food was lukewarm when it arrived. Staff didn\'t seem to care.'],
        ['rating' => 2, 'comment' => 'Below expectations. Menu sounded promising but execution was poor. Overpriced for the quality.'],
        ['rating' => 2, 'comment' => 'Had high hopes but left unsatisfied. Several issues with our order and no apology from staff.'],

        // Poor (1 star)
        ['rating' => 1, 'comment' => 'Terrible experience. Food was cold, service was rude, and the place was dirty. Would not recommend.'],
        ['rating' => 1, 'comment' => 'Worst dining experience in a long time. Completely unacceptable quality and service. Avoid this place.'],
        ['rating' => 1, 'comment' => 'Absolutely awful. Food was inedible, staff were unhelpful, and we waited over an hour. Never again.'],
        ['rating' => 1, 'comment' => 'Do not waste your money here. Poor hygiene, terrible food, and appalling customer service.'],
    ],

    'question_categories' => [
        'Staff' => [
            'Staff Performance',
            'Staff Behavior',
            'Staff Professionalism'
        ],
        'Experience' => [
            'Overall Satisfaction',
            'Atmosphere',
            'Value for Money'
        ],
        'Food & Beverage' => [
            'Taste & Quality',
            'Presentation',
            'Freshness'
        ],
        'Operations' => [
            'Timeliness',
            'Packaging',
            'Accuracy',
            'Setup',
            'Special Requests'
        ],
    ],

    'question_category_mappings' => [
        // Overall questions
        'How would you rate your overall experience?' => 'Overall Satisfaction',
        'Would you recommend us to friends and family?' => 'Overall Satisfaction',
        'How likely are you to return?' => 'Overall Satisfaction',
        'How satisfied are you with your visit today?' => 'Overall Satisfaction',
        'How do you rate our establishment compared to competitors?' => 'Value for Money',
        'How professional was our service?' => 'Staff Professionalism',
        'How welcoming was our staff?' => 'Staff Behavior',
        'How well did we meet your expectations?' => 'Overall Satisfaction',
        'How would you rate the overall quality?' => 'Overall Satisfaction',
        'How satisfied are you with your overall experience?' => 'Overall Satisfaction',

        // Dine-in Restaurant
        'How would you rate the taste of your meal?' => 'Taste & Quality',
        'How would you rate food presentation?' => 'Presentation',
        'How fresh were the ingredients?' => 'Freshness',
        'How appropriate were the portion sizes?' => 'Taste & Quality',
        'How would you rate the menu variety?' => 'Overall Satisfaction',
        'How was the temperature of your food?' => 'Taste & Quality',

        // Takeaway Service
        'How friendly was the pickup staff?' => 'Staff Behavior',
        'How quick was the order preparation?' => 'Timeliness',
        'How well was your order packaged?' => 'Packaging',
        'How accurate was your takeaway order?' => 'Accuracy',
        'How satisfied were you with pickup instructions?' => 'Overall Satisfaction',
        'How would you rate the takeaway experience?' => 'Overall Satisfaction',

        // Delivery Service
        'How timely was the delivery?' => 'Timeliness',
        'How well-packaged was your order?' => 'Packaging',
        'How accurate was your order?' => 'Accuracy',
        'How satisfied were you with the delivery experience?' => 'Overall Satisfaction',
        'How professional was the delivery driver?' => 'Staff Professionalism',
        'How would you rate the food temperature upon arrival?' => 'Taste & Quality',

        // Catering Service
        'How satisfied were you with catering setup?' => 'Setup',
        'How would you rate food variety for catering?' => 'Overall Satisfaction',
        'How professional was the catering staff?' => 'Staff Professionalism',
        'How well did we accommodate dietary requests?' => 'Special Requests',
        'How would you rate the catering presentation?' => 'Presentation',
        'How satisfied were you with catering timing?' => 'Timeliness',

        // Coffee Shop
        'How would you rate coffee quality?' => 'Taste & Quality',
        'How friendly was the barista?' => 'Staff Behavior',
        'How quick was the service?' => 'Timeliness',
        'How would you rate pastry freshness?' => 'Freshness',
        'How comfortable was the seating area?' => 'Atmosphere',
        'How satisfied were you with the atmosphere?' => 'Atmosphere',
    ],
];
