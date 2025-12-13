<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIProcessor
{
    /**
     * Step 1: AI Moderation Pipeline (Improved)
     */
   public static function aiModeration($text)
{
    $abusivePatterns = ['idiot', 'stupid', 'shit', 'fuck', 'asshole', 'bastard', 'dick', 'cunt', 'bitch', 'hell', 'damn'];
    $hateSpeechIndicators = ['racist', 'sexist', 'discriminat', 'bigot', 'homophobic', 'transphobic', 'hate', 'prejudice'];
    $spamPatterns = ['http://', 'https://', 'www.', 'buy now', 'click here', 'discount', 'offer', 'earn money', 'free', 'click'];
    
    // Moderate negative but acceptable criticism
    $moderateCriticism = [
        'rude' => 1,
        'terrible' => 1,
        'awful' => 1,
        'horrible' => 1,
        'disgusting' => 1,
        'worst' => 2,
        'hate' => 2,
        'never coming back' => 1,
        'avoid' => 1
    ];
    
    // Context-aware filtering
    $acceptableCriticism = ['terrible', 'awful', 'horrible', 'hate', 'bad', 'poor', 'rude', 'disgusting'];
    
    $issues = [];
    $severity = 0;
    $textLower = strtolower($text);
    
    // Check for abusive words
    foreach ($abusivePatterns as $pattern) {
        if (strpos($textLower, $pattern) !== false && !self::isNegated($textLower, $pattern)) {
            $issues[] = 'abusive_language';
            $severity += 3; // High severity for actual abuse
        }
    }
    
    // Check for hate speech
    foreach ($hateSpeechIndicators as $indicator) {
        if (strpos($textLower, $indicator) !== false && !self::isNegated($textLower, $indicator)) {
            $issues[] = 'hate_speech';
            $severity += 4; // Highest severity
        }
    }
    
    // Check for spam
    foreach ($spamPatterns as $pattern) {
        if (strpos($textLower, $pattern) !== false) {
            $issues[] = 'spam_content';
            $severity += 2;
        }
    }
    
    // Check for moderate criticism (flag for review)
    foreach ($moderateCriticism as $word => $wordSeverity) {
        if (strpos($textLower, $word) !== false && !self::isNegated($textLower, $word)) {
            // Only flag if it's truly negative (not in acceptable criticism context)
            $isAcceptable = false;
            foreach ($acceptableCriticism as $acceptable) {
                if ($word === $acceptable) {
                    // Check if it's in a constructive context
                    if (preg_match('/\b(but|however|although|though)\b/i', $text)) {
                        $isAcceptable = true;
                    }
                    break;
                }
            }
            
            if (!$isAcceptable) {
                $issues[] = 'strong_criticism';
                $severity += $wordSeverity;
            }
        }
    }
    
    // Check for excessive negativity (multiple strong negative words)
    $negativeCount = 0;
    foreach ($moderateCriticism as $word => $severityValue) {
        if (strpos($textLower, $word) !== false && !self::isNegated($textLower, $word)) {
            $negativeCount++;
        }
    }
    
    if ($negativeCount >= 3) {
        $issues[] = 'excessive_negativity';
        $severity += 2;
    }
    
    $issues = array_values(array_unique($issues));
    
    // Determine action based on severity
    $action = 'allow';
    $shouldBlock = false;
    $actionMessage = 'Content approved';
    
    if ($severity >= 5) {
        $action = 'block';
        $shouldBlock = true;
        $actionMessage = 'Content blocked due to inappropriate content';
    } elseif ($severity >= 3) {
        $action = 'flag_for_review';
        $actionMessage = 'Content flagged for admin review';
    } elseif ($severity >= 1) {
        $action = 'warn';
        $actionMessage = 'Content contains potentially inappropriate language';
    }
    
    return [
        'issues_found' => $issues,
        'severity_score' => $severity,
        'action_taken' => $action,
        'should_block' => $shouldBlock,
        'action_message' => $actionMessage
    ];
}
    /**
     * Step 2: Improved Sentiment Analysis with Better Negation Handling
     */
    public static function analyzeSentiment($text)
    {
        $api_key = config('services.huggingface.api_key');
        
        if (empty($api_key)) {
            Log::warning('HuggingFace API key not configured, using improved fallback sentiment analysis');
            return self::analyzeSentimentImprovedFallback($text);
        }

        try {
            $cacheKey = 'sentiment_' . md5($text);
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api-inference.huggingface.co/models/cardiffnlp/twitter-roberta-base-sentiment-latest', [
                'inputs' => substr($text, 0, 500)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::debug('Sentiment API response', ['response' => $data]);
                
                if (isset($data[0]) && is_array($data[0])) {
                    $highestScore = 0;
                    $sentimentScore = 0.5;
                    
                    foreach ($data[0] as $item) {
                        if (isset($item['score']) && $item['score'] > $highestScore) {
                            $highestScore = $item['score'];
                            $label = $item['label'] ?? '';
                            
                            if (strpos($label, 'positive') !== false || $label === 'LABEL_2') {
                                $sentimentScore = 0.7 + ($item['score'] * 0.3);
                            } elseif (strpos($label, 'negative') !== false || $label === 'LABEL_0') {
                                $sentimentScore = 0.3 - ($item['score'] * 0.3);
                            } elseif (strpos($label, 'neutral') !== false || $label === 'LABEL_1') {
                                $sentimentScore = 0.4 + ($item['score'] * 0.2);
                            }
                        }
                    }
                    
                    $sentimentScore = min(max($sentimentScore, 0.0), 1.0);
                    
                    // Apply negation correction
                    $sentimentScore = self::applyNegationCorrection($text, $sentimentScore);
                    
                    Cache::put($cacheKey, $sentimentScore, 3600);
                    return $sentimentScore;
                }
            }
            
            Log::warning('Sentiment API returned unexpected format, using fallback');
            
        } catch (\Exception $e) {
            Log::error('Sentiment analysis API failed', [
                'error' => $e->getMessage()
            ]);
        }

        return self::analyzeSentimentImprovedFallback($text);
    }

    /**
     * Apply negation correction to sentiment score
     */
    private static function applyNegationCorrection($text, $originalScore)
    {
        $textLower = strtolower($text);
        
        // Strong negation patterns
        $strongNegationPatterns = [
            '/not happy/i', '/not good/i', '/not satisfied/i', '/not pleased/i',
            '/not impressed/i', '/not recommended/i', '/not coming back/i',
            '/never again/i', '/would not recommend/i'
        ];
        
        // Weak negation patterns
        $weakNegationPatterns = [
            '/could be better/i', '/not bad/i', '/not great/i', '/not the best/i',
            '/nothing special/i', '/not amazing/i'
        ];
        
        // Check for strong negations
        foreach ($strongNegationPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                // Invert sentiment for strong negations
                if ($originalScore > 0.5) {
                    return max(0.1, 1.0 - $originalScore - 0.2);
                }
                return min(0.3, $originalScore);
            }
        }
        
        // Check for weak negations
        foreach ($weakNegationPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return min(0.5, $originalScore);
            }
        }
        
        return $originalScore;
    }

    /**
     * Improved Fallback Sentiment Analysis with Better Negation Handling
     */
    private static function analyzeSentimentImprovedFallback($text)
    {
        $textLower = strtolower($text);
        
        // Enhanced word lists with weights
        $positiveWords = [
            'excellent' => 2.0, 'amazing' => 2.0, 'fantastic' => 2.0, 'outstanding' => 2.0,
            'perfect' => 2.0, 'wonderful' => 2.0, 'delicious' => 1.5, 'love' => 1.5,
            'great' => 1.5, 'awesome' => 1.5, 'superb' => 1.5, 'best' => 1.5,
            'good' => 1.0, 'nice' => 1.0, 'enjoy' => 1.0, 'happy' => 1.0,
            'satisfied' => 1.0, 'impressed' => 1.0, 'pleasant' => 1.0, 'friendly' => 0.5,
            'helpful' => 0.5, 'polite' => 0.5, 'clean' => 0.5, 'comfortable' => 0.5,
            'recommend' => 1.0, 'excellence' => 2.0, 'flawless' => 2.0, 'absolutely' => 1.0,
            'definitely' => 1.0, 'highly' => 1.0
        ];
        
        $negativeWords = [
            'terrible' => 2.0, 'awful' => 2.0, 'horrible' => 2.0, 'disgusting' => 2.0,
            'worst' => 2.0, 'hate' => 2.0, 'dislike' => 1.5, 'poor' => 1.5,
            'bad' => 1.5, 'disappointing' => 1.5, 'unhappy' => 1.5, 'dissatisfied' => 1.5,
            'rude' => 1.5, 'unfriendly' => 1.5, 'unhelpful' => 1.5, 'slow' => 1.0,
            'cold' => 1.0, 'tasteless' => 1.0, 'bland' => 1.0, 'dirty' => 1.0,
            'messy' => 1.0, 'unclean' => 1.0, 'uncomfortable' => 1.0, 'overpriced' => 1.0,
            'expensive' => 0.5, 'wait' => 0.5, 'delay' => 0.5, 'late' => 0.5,
            'never' => 1.0, 'worst' => 2.0, 'avoid' => 1.0
        ];
        
        // Calculate weighted scores
        $posScore = 0;
        $negScore = 0;
        
        // First pass: identify negation context at sentence level
        $sentences = preg_split('/[.!?]+/', $textLower);
        $negationSentences = [];
        
        foreach ($sentences as $sentence) {
            $negationWords = ['not', 'no', 'never', 'nothing', 'isn\'t', 'wasn\'t', 'aren\'t', 'weren\'t', 'doesn\'t', 'don\'t', 'didn\'t'];
            foreach ($negationWords as $negWord) {
                if (strpos($sentence, $negWord) !== false) {
                    $negationSentences[] = trim($sentence);
                    break;
                }
            }
        }
        
        // Second pass: calculate scores with negation awareness
        foreach ($sentences as $sentence) {
            $isNegated = in_array(trim($sentence), $negationSentences);
            
            foreach ($positiveWords as $word => $weight) {
                if (strpos($sentence, $word) !== false) {
                    if ($isNegated) {
                        $negScore += $weight; // Negated positive becomes negative
                    } else {
                        $posScore += $weight;
                    }
                }
            }
            
            foreach ($negativeWords as $word => $weight) {
                if (strpos($sentence, $word) !== false) {
                    if ($isNegated) {
                        $posScore += $weight; // Negated negative becomes positive
                    } else {
                        $negScore += $weight;
                    }
                }
            }
        }
        
        // Special handling for common patterns
        if (preg_match('/not happy/i', $text)) {
            $posScore = max(0, $posScore - 2);
            $negScore += 2;
        }
        
        if (preg_match('/not bad/i', $text)) {
            // "not bad" is slightly positive
            $posScore += 0.5;
            $negScore = max(0, $negScore - 0.5);
        }
        
        // Calculate final sentiment
        $totalScore = $posScore + $negScore;
        
        if ($totalScore === 0) {
            // Check for neutral indicators
            $neutralIndicators = ['okay', 'fine', 'average', 'normal', 'regular', 'decent', 'acceptable'];
            foreach ($neutralIndicators as $indicator) {
                if (strpos($textLower, $indicator) !== false) {
                    return 0.5;
                }
            }
            
            // Default based on overall tone
            if (strpos($textLower, '!') !== false && strlen($text) > 50) {
                return 0.6; // Likely positive if exclamation
            }
            return 0.5;
        }
        
        $sentimentRatio = $posScore / $totalScore;
        
        // Map to our scale with better granularity
        if ($sentimentRatio >= 0.8) return 0.9; // Very positive
        if ($sentimentRatio >= 0.7) return 0.8; // Positive
        if ($sentimentRatio >= 0.6) return 0.7; // Somewhat positive
        if ($sentimentRatio >= 0.5) return 0.6; // Slightly positive
        if ($sentimentRatio >= 0.4) return 0.5; // Neutral
        if ($sentimentRatio >= 0.3) return 0.4; // Slightly negative
        if ($sentimentRatio >= 0.2) return 0.3; // Somewhat negative
        if ($sentimentRatio >= 0.1) return 0.2; // Negative
        return 0.1; // Very negative
    }
    
    /**
     * Check if a word is negated in context (improved)
     */
    private static function isNegated($text, $word)
    {
        $sentences = preg_split('/[.!?]+/', strtolower($text));
        
        foreach ($sentences as $sentence) {
            $pos = strpos($sentence, $word);
            if ($pos === false) continue;
            
            // Check for negation words in the same sentence
            $negationWords = ['not', 'no', 'never', 'nothing', 'isn\'t', 'wasn\'t', 'aren\'t', 'weren\'t', 'doesn\'t', 'don\'t', 'didn\'t'];
            
            foreach ($negationWords as $negWord) {
                if (strpos($sentence, $negWord) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Step 3: Topic Extraction (Improved with more context)
     */
   public static function extractTopics($text)
{
    $textLower = strtolower($text);
    $topics = [];
    
    // Expanded topic definitions with multiple keywords
    $topicDefinitions = [
        'wait time' => [
            'keywords' => ['wait', 'queue', 'long', 'slow', 'fast', 'quick', 'time', 'minutes', 'hours', 'delay'],
            'context_positive' => ['fast', 'quick', 'prompt'],
            'context_negative' => ['long', 'slow', 'delay']
        ],
        'cleanliness' => [
            'keywords' => ['clean', 'dirty', 'messy', 'tidy', 'hygiene', 'sanitary', 'spotless', 'filthy'],
            'context_positive' => ['clean', 'spotless', 'tidy'],
            'context_negative' => ['dirty', 'messy', 'filthy']
        ],
        'staff politeness' => [
            'keywords' => ['polite', 'rude', 'friendly', 'unfriendly', 'courteous', 'respectful', 'attitude'],
            'context_positive' => ['polite', 'friendly', 'courteous'],
            'context_negative' => ['rude', 'unfriendly', 'disrespectful']
        ],
        'food quality' => [
            'keywords' => ['taste', 'flavor', 'delicious', 'tasty', 'bland', 'fresh', 'stale', 'cooked', 'raw'],
            'context_positive' => ['delicious', 'tasty', 'fresh'],
            'context_negative' => ['bland', 'stale', 'raw']
        ],
        'service quality' => [
            'keywords' => ['service', 'helpful', 'unhelpful', 'attentive', 'ignore', 'care', 'professional'],
            'context_positive' => ['helpful', 'attentive', 'professional'],
            'context_negative' => ['unhelpful', 'ignore', 'uncaring']
        ],
        'atmosphere' => [
            'keywords' => ['ambiance', 'atmosphere', 'vibe', 'environment', 'decor', 'music', 'lighting', 'noisy'],
            'context_positive' => ['nice', 'great', 'wonderful', 'beautiful'],
            'context_negative' => ['noisy', 'poor', 'bad']
        ],
        'price value' => [
            'keywords' => ['price', 'expensive', 'cheap', 'value', 'worth', 'overpriced', 'affordable', 'cost'],
            'context_positive' => ['affordable', 'worth', 'value'],
            'context_negative' => ['expensive', 'overpriced', 'costly']
        ],
        'portion size' => [
            'keywords' => ['portion', 'size', 'large', 'small', 'generous', 'meager', 'enough', 'fill'],
            'context_positive' => ['generous', 'large', 'enough'],
            'context_negative' => ['small', 'meager', 'not enough']
        ],
        'presentation' => [
            'keywords' => ['presentation', 'look', 'appearance', 'beautiful', 'ugly', 'plating', 'served'],
            'context_positive' => ['beautiful', 'nice', 'great'],
            'context_negative' => ['ugly', 'poor', 'bad']
        ],
        'location' => [
            'keywords' => ['location', 'place', 'area', 'accessible', 'remote', 'convenient', 'parking'],
            'context_positive' => ['convenient', 'accessible', 'great'],
            'context_negative' => ['remote', 'inconvenient', 'poor']
        ],
        'menu variety' => [
            'keywords' => ['menu', 'variety', 'selection', 'options', 'choices', 'diverse', 'limited'],
            'context_positive' => ['varied', 'diverse', 'many'],
            'context_negative' => ['limited', 'few', 'poor']
        ],
    ];
    
    // First pass: check for specific topic keywords
    foreach ($topicDefinitions as $topic => $data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                // Check if it's negated (e.g., "not dirty" shouldn't trigger cleanliness issue)
                if (!self::isNegated($textLower, $keyword)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }
    }
    
    // Second pass: context-based extraction for short/enthusiastic reviews
    if (empty($topics)) {
        // Check for general positive/negative indicators
        $positiveIndicators = ['excellent', 'best', 'amazing', 'wonderful', 'love', 'perfect', 'great', 'fantastic', 'outstanding'];
        $negativeIndicators = ['terrible', 'awful', 'horrible', 'worst', 'bad', 'poor', 'disappointing'];
        
        $hasPositive = false;
        $hasNegative = false;
        
        foreach ($positiveIndicators as $indicator) {
            if (strpos($textLower, $indicator) !== false && !self::isNegated($textLower, $indicator)) {
                $hasPositive = true;
                break;
            }
        }
        
        foreach ($negativeIndicators as $indicator) {
            if (strpos($textLower, $indicator) !== false && !self::isNegated($textLower, $indicator)) {
                $hasNegative = true;
                break;
            }
        }
        
        // Extract topics based on context
        if ($hasPositive || $hasNegative) {
            // Food-related context
            if (preg_match('/\b(food|dish|meal|taste|flavor|cuisine|menu|restaurant|eat|dining)\b/i', $text)) {
                $topics[] = 'food quality';
            }
            
            // Service-related context
            if (preg_match('/\b(service|staff|waiter|waitress|server|host|manager|employee)\b/i', $text)) {
                $topics[] = 'service quality';
            }
            
            // Ambiance-related context
            if (preg_match('/\b(place|location|restaurant|establishment|venue|spot|joint)\b/i', $text)) {
                $topics[] = 'location';
            }
            
            // Price-related context
            if (preg_match('/\b(price|cost|expensive|cheap|affordable|overpriced|value|worth)\b/i', $text)) {
                $topics[] = 'price value';
            }
        }
    }
    
    // Remove "location" if not actually mentioned (false positive from Review #8)
    if (in_array('location', $topics)) {
        $locationKeywords = ['location', 'place', 'area', 'accessible', 'remote', 'convenient', 'parking', 'address', 'find', 'located'];
        $hasLocation = false;
        foreach ($locationKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                $hasLocation = true;
                break;
            }
        }
        if (!$hasLocation) {
            $topics = array_diff($topics, ['location']);
        }
    }
    
    // Remove duplicates and return
    return array_values(array_unique($topics));
}

    /**
     * Step 4: Staff Performance Analysis (Context-Aware)
     */
  public static function analyzeStaffPerformance($text, $staff_id, $sentiment_score = null)
{
    if (!$staff_id) {
        return [];
    }
    
    if ($sentiment_score === null) {
        $sentiment_score = self::analyzeSentiment($text);
    }
    
    $textLower = strtolower($text);
    $suggestions = [];
    
    // Define performance indicators with context
    $indicators = [
        'communication' => [
            'keywords' => ['explain', 'listen', 'communication', 'understand', 'clear', 'confusing'],
            'positive_context' => ['well', 'clearly', 'good', 'excellent', 'helpful'],
            'negative_context' => ['poorly', 'not', 'unclear', 'confusing', 'bad']
        ],
        'service_speed' => [
            'keywords' => ['slow', 'fast', 'wait', 'quick', 'delay', 'time', 'minutes', 'prompt'],
            'positive_context' => ['prompt', 'quick', 'fast', 'efficient', 'timely'],
            'negative_context' => ['slow', 'delay', 'long', 'late', 'waiting']
        ],
        'product_knowledge' => [
            'keywords' => ['knowledge', 'inform', 'explain', 'helpful', 'expert', 'familiar'],
            'positive_context' => ['knowledgeable', 'expert', 'well-informed', 'helpful'],
            'negative_context' => ['uninformed', 'ignorant', 'not know', 'no idea']
        ],
        'attitude' => [
            'keywords' => ['friendly', 'rude', 'polite', 'nice', 'unprofessional', 'welcoming'],
            'positive_context' => ['friendly', 'polite', 'welcoming', 'professional', 'courteous'],
            'negative_context' => ['rude', 'unfriendly', 'impolite', 'unprofessional']
        ],
        'attention' => [
            'keywords' => ['attentive', 'ignore', 'care', 'neglect', 'check', 'monitor'],
            'positive_context' => ['attentive', 'caring', 'checking', 'monitoring'],
            'negative_context' => ['ignored', 'neglected', 'inattentive', 'didn\'t care']
        ]
    ];
    
    // Aggressive detection for negative behavior patterns
    $negativePatterns = [
        '/poor service/i' => 'Customer service excellence training recommended',
        '/bad behaviour/i' => 'Customer service excellence training recommended',
        '/really bad/i' => 'Customer service excellence training recommended',
        '/terrible service/i' => 'Customer service excellence training recommended',
        '/very poor/i' => 'Customer service excellence training recommended',
        '/not helpful/i' => 'Customer service excellence training recommended',
        '/unhelpful/i' => 'Customer service excellence training recommended',
        '/ignored me/i' => 'Needs attentiveness and customer care training',
        '/didn\'t care/i' => 'Needs attentiveness and customer care training',
        '/no knowledge/i' => 'Needs product knowledge workshop',
        '/didn\'t know/i' => 'Needs product knowledge workshop'
    ];
    
    // Check for negative patterns
    foreach ($negativePatterns as $pattern => $suggestion) {
        if (preg_match($pattern, $text) && !self::isNegatedPattern($text, $pattern)) {
            $suggestions[] = $suggestion;
        }
    }
    
    // Analyze each indicator with context
    foreach ($indicators as $indicator => $data) {
        $found = false;
        $is_negative = false;
        
        foreach ($data['keywords'] as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                $found = true;
                
                // Check for negation
                if (self::isNegated($textLower, $keyword)) {
                    // If a negative word is negated, it might be positive
                    if (in_array($keyword, ['rude', 'unfriendly', 'slow', 'unhelpful', 'ignorant'])) {
                        continue; // Skip negative suggestion for negated negative words
                    }
                }
                
                // Check context
                foreach ($data['negative_context'] as $negContext) {
                    if (strpos($textLower, $negContext) !== false && 
                        !self::isNegated($textLower, $negContext)) {
                        $is_negative = true;
                        break 2;
                    }
                }
                
                // Check positive context
                foreach ($data['positive_context'] as $posContext) {
                    if (strpos($textLower, $posContext) !== false) {
                        $is_negative = false;
                        break 2;
                    }
                }
            }
        }
        
        // Generate suggestions for negative findings or if sentiment is low
        if ($found && ($is_negative || $sentiment_score < 0.4)) {
            switch ($indicator) {
                case 'communication':
                    $suggestions[] = 'Needs better communication skills training';
                    break;
                case 'service_speed':
                    $suggestions[] = 'Requires efficiency and time management training';
                    break;
                case 'product_knowledge':
                    $suggestions[] = 'Needs product knowledge workshop';
                    break;
                case 'attitude':
                    $suggestions[] = 'Customer service excellence training recommended';
                    break;
                case 'attention':
                    $suggestions[] = 'Needs attentiveness and customer care training';
                    break;
            }
        }
    }
    
    // Ensure at least one suggestion for clearly negative reviews about staff
    if (empty($suggestions) && $sentiment_score < 0.4) {
        if (preg_match('/\b(staff|waiter|waitress|server|employee|worker)\b/i', $text)) {
            $suggestions[] = 'General customer service training recommended';
        }
    }
    
    return array_unique($suggestions);
}

/**
 * Helper method to check if a pattern is negated
 */
private static function isNegatedPattern($text, $pattern)
{
    $textLower = strtolower($text);
    $negationWords = ['not', 'no', 'never', 'isn\'t', 'wasn\'t', 'aren\'t', 'weren\'t', 'doesn\'t', 'don\'t', 'didn\'t'];
    
    // Extract the keyword from the pattern
    preg_match('/\/(.*?)\//', $pattern, $matches);
    if (isset($matches[1])) {
        $keyword = strtolower($matches[1]);
        
        $sentences = preg_split('/[.!?]+/', $textLower);
        foreach ($sentences as $sentence) {
            if (strpos($sentence, $keyword) !== false) {
                foreach ($negationWords as $negWord) {
                    if (strpos($sentence, $negWord) !== false) {
                        return true;
                    }
                }
            }
        }
    }
    
    return false;
}

    /**
     * Step 5: Generate Recommendations
     */
    public static function generateRecommendations($topics, $sentiment_score)
    {
        $recommendations = [];
        
        if (empty($topics)) {
            return $recommendations;
        }
        
        $recommendationMap = [
            'wait time' => 'Consider optimizing staffing schedules during peak hours',
            'cleanliness' => 'Implement more frequent cleaning checks',
            'staff politeness' => 'Provide additional customer service training',
            'food quality' => 'Review quality control procedures in the kitchen',
            'service quality' => 'Consider implementing service quality monitoring',
            'atmosphere' => 'Review ambient factors like lighting and music',
            'price value' => 'Conduct competitive pricing analysis',
            'portion size' => 'Review portion consistency across servings',
            'presentation' => 'Provide plating and presentation training',
            'location' => 'Improve signage and accessibility information',
            'menu variety' => 'Regularly update menu based on customer feedback'
        ];
        
        foreach ($topics as $topic) {
            if (isset($recommendationMap[$topic])) {
                // Only add recommendation if sentiment is neutral or negative
                if ($sentiment_score <= 0.6) {
                    $recommendations[] = $recommendationMap[$topic];
                }
            }
        }
        
        return array_unique($recommendations);
    }

    /**
     * Step 6: Improved Emotion Detection with Consistency
     */
    public static function detectEmotion($text)
    {
        $api_key = config('services.huggingface.api_key');
        
        if (empty($api_key)) {
            return self::detectEmotionImprovedFallback($text);
        }
        
        try {
            $cacheKey = 'emotion_' . md5($text);
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api-inference.huggingface.co/models/j-hartmann/emotion-english-distilroberta-base', [
                'inputs' => substr($text, 0, 500)
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data[0]) && isset($data[0][0]['label'])) {
                    $emotion = $data[0][0]['label'];
                    Cache::put($cacheKey, $emotion, 3600);
                    return $emotion;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Emotion detection failed', [
                'error' => $e->getMessage()
            ]);
        }
        
        return self::detectEmotionImprovedFallback($text);
    }
    
    private static function detectEmotionImprovedFallback($text)
    {
        $textLower = strtolower($text);
        $sentiment = self::analyzeSentiment($text);
        
        // First check for sarcasm/contradiction
        if (($sentiment < 0.4 && preg_match('/great|excellent|amazing|wonderful/i', $text)) ||
            ($sentiment > 0.7 && preg_match('/terrible|awful|horrible|worst/i', $text))) {
            return 'sarcasm';
        }
        
        // Enhanced emotion detection with context
        $emotionPatterns = [
            'anger' => [
                'keywords' => ['angry', 'mad', 'furious', 'outrage', 'rage', 'hate', 'disgust', 'horrible', 'terrible', 'awful', 'worst', 'rude', 'unhelpful', 'never coming back'],
                'context' => ['very', 'extremely', 'completely', 'totally', 'absolutely'],
                'weight' => 0
            ],
            'joy' => [
                'keywords' => ['happy', 'joy', 'delighted', 'excited', 'love', 'great', 'wonderful', 'amazing', 'fantastic', 'excellent', 'perfect', 'outstanding', 'best ever', 'definitely return'],
                'context' => ['very', 'so', 'really', 'absolutely', 'completely'],
                'weight' => 0
            ],
            'sadness' => [
                'keywords' => ['sad', 'unhappy', 'disappointed', 'depressed', 'sorry', 'regret', 'disappointing', 'poor', 'bad', 'not happy'],
                'context' => ['very', 'so', 'really', 'deeply', 'extremely'],
                'weight' => 0
            ],
            'surprise' => [
                'keywords' => ['surprise', 'shocked', 'amazed', 'astonished', 'unexpected', 'wow', 'incredible', 'unbelievable'],
                'context' => ['very', 'completely', 'totally', 'absolutely'],
                'weight' => 0
            ],
            'fear' => [
                'keywords' => ['scared', 'afraid', 'fear', 'worried', 'anxious', 'nervous', 'concerned', 'unsafe'],
                'context' => ['very', 'extremely', 'really'],
                'weight' => 0
            ],
            'neutral' => [
                'keywords' => ['okay', 'fine', 'average', 'normal', 'regular', 'standard', 'decent', 'acceptable', 'nothing special'],
                'context' => [],
                'weight' => 0
            ]
        ];
        
        // Calculate weights for each emotion
        foreach ($emotionPatterns as $emotion => &$data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    // Check for negation
                    if (self::isNegated($textLower, $keyword)) {
                        // Negated emotion gets opposite weight
                        if (in_array($emotion, ['joy', 'surprise'])) {
                            $data['weight'] -= 1;
                        } elseif (in_array($emotion, ['anger', 'sadness', 'fear'])) {
                            $data['weight'] += 0.5; // Less negative if negated
                        }
                    } else {
                        $data['weight'] += 1;
                        
                        // Check for intensifying context
                        foreach ($data['context'] as $contextWord) {
                            $pattern = '/' . preg_quote($contextWord) . '\s+\w*\s*' . preg_quote($keyword) . '/i';
                            if (preg_match($pattern, $textLower)) {
                                $data['weight'] += 0.5;
                            }
                        }
                    }
                }
            }
        }
        
        // Find the emotion with highest weight
        $maxWeight = 0;
        $detectedEmotion = 'neutral';
        
        foreach ($emotionPatterns as $emotion => $data) {
            if ($data['weight'] > $maxWeight) {
                $maxWeight = $data['weight'];
                $detectedEmotion = $emotion;
            }
        }
        
        // If no specific emotion detected, use sentiment as fallback with consistency
        if ($maxWeight === 0) {
            if ($sentiment >= 0.7) return 'joy';
            if ($sentiment <= 0.3) return 'sadness';
            return 'neutral';
        }
        
        // Ensure consistency with sentiment
        if (($detectedEmotion === 'joy' && $sentiment < 0.4) || 
            (in_array($detectedEmotion, ['anger', 'sadness']) && $sentiment > 0.7)) {
            // Inconsistent, adjust based on sentiment
            if ($sentiment < 0.4) return 'sadness';
            if ($sentiment > 0.7) return 'joy';
        }
        
        return $detectedEmotion;
    }

    /**
     * Step 7: Improved Key Phrases Extraction
     */
    public static function extractKeyPhrases($text)
    {
        $phrases = self::extractKeyPhrasesImproved($text);
        
        if (!empty($phrases)) {
            // Filter out generic phrases
            $genericPhrases = ['this', 'that', 'the', 'and', 'but', 'with', 'for', 'from', 'have', 'has', 'had', 'was', 'were', 'are', 'is', 'very', 'really', 'their', 'they', 'them', 'these', 'those', 'there'];
            $filteredPhrases = array_diff($phrases, $genericPhrases);
            
            return array_slice(array_values($filteredPhrases), 0, 5);
        }
        
        return self::extractImportantWordsImproved($text);
    }
    
   private static function extractKeyPhrasesImproved($text)
{
    // Clean text
    $text = preg_replace('/[^\w\s\']/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Define stop words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'this', 'that', 'these', 'those', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'very', 'really', 'just', 'about', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', 'should', 'now'];
    
    // Extract 2-3 word phrases (bigrams and trigrams)
    $words = preg_split('/\s+/', strtolower(trim($text)));
    $phrases = [];
    
    // Create bigrams (2-word phrases)
    for ($i = 0; $i < count($words) - 1; $i++) {
        $word1 = $words[$i];
        $word2 = $words[$i + 1];
        
        // Skip if either word is a stop word
        if (in_array($word1, $stopWords) || in_array($word2, $stopWords)) {
            continue;
        }
        
        // Skip if words are too short
        if (strlen($word1) < 3 || strlen($word2) < 3) {
            continue;
        }
        
        // Skip nonsense combinations
        $nonsensePatterns = ['okay nothing', 'nothing special', 'unhelpful terrible', 'food wasn\'t', 'wasn\'t bad', 'overpriced never', 'never coming', 'coming back'];
        $phrase = $word1 . ' ' . $word2;
        
        $isNonsense = false;
        foreach ($nonsensePatterns as $nonsense) {
            if (strpos($phrase, $nonsense) !== false) {
                $isNonsense = true;
                break;
            }
        }
        
        if (!$isNonsense) {
            $phrases[] = $phrase;
        }
    }
    
    // Create trigrams (3-word phrases) - only for meaningful combinations
    for ($i = 0; $i < count($words) - 2; $i++) {
        $word1 = $words[$i];
        $word2 = $words[$i + 1];
        $word3 = $words[$i + 2];
        
        // Skip if any word is a stop word
        if (in_array($word1, $stopWords) || in_array($word2, $stopWords) || in_array($word3, $stopWords)) {
            continue;
        }
        
        // Skip if words are too short
        if (strlen($word1) < 3 || strlen($word2) < 3 || strlen($word3) < 3) {
            continue;
        }
        
        // Check if it's a meaningful phrase
        $phrase = $word1 . ' ' . $word2 . ' ' . $word3;
        
        // Skip phrases that don't make sense
        $invalidPatterns = [
            '/^okay\s+nothing\s+special$/',
            '/^unhelpful\s+terrible\s+experience$/',
            '/^overpriced\s+never\s+coming$/',
            '/^never\s+coming\s+back$/',
            '/^food\s+wasn\'t\s+bad$/'
        ];
        
        $isValid = true;
        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $phrase)) {
                $isValid = false;
                break;
            }
        }
        
        if ($isValid) {
            $phrases[] = $phrase;
        }
    }
    
    // Count phrase frequency
    $phraseCounts = array_count_values($phrases);
    arsort($phraseCounts);
    
    // Return top phrases (max 5)
    $topPhrases = array_keys(array_slice($phraseCounts, 0, 5));
    
    // If we have very few phrases, extract meaningful 2-word combinations
    if (count($topPhrases) < 3) {
        $meaningfulPairs = [
            'poor service', 'bad behaviour', 'cold tasteless', 'long wait', 
            'friendly staff', 'excellent service', 'great food', 'best restaurant',
            'rude staff', 'dirty place', 'overpriced food', 'terrible experience'
        ];
        
        $textLower = strtolower($text);
        foreach ($meaningfulPairs as $pair) {
            if (strpos($textLower, $pair) !== false && !in_array($pair, $topPhrases)) {
                $topPhrases[] = $pair;
            }
        }
    }
    
    return array_slice($topPhrases, 0, 5);
}
    
    private static function extractImportantWordsImproved($text)
    {
        $textLower = strtolower($text);
        $importantWords = [];
        
        // Define important word categories with weights
        $wordCategories = [
            // Food related
            'food' => ['food', 'dish', 'meal', 'dinner', 'lunch', 'breakfast', 'cuisine', 'menu'],
            'taste' => ['taste', 'flavor', 'delicious', 'tasty', 'savory', 'bland', 'spicy', 'sweet'],
            'quality' => ['quality', 'fresh', 'cooked', 'raw', 'hot', 'cold', 'warm', 'moist', 'dry'],
            
            // Service related
            'service' => ['service', 'staff', 'waiter', 'waitress', 'server', 'host', 'manager'],
            'behavior' => ['friendly', 'rude', 'polite', 'helpful', 'unhelpful', 'attentive', 'ignored'],
            'efficiency' => ['efficient', 'slow', 'fast', 'quick', 'prompt', 'delayed', 'timely'],
            
            // Ambiance related
            'ambiance' => ['ambiance', 'atmosphere', 'environment', 'vibe', 'decor', 'lighting', 'music'],
            'comfort' => ['comfortable', 'uncomfortable', 'cozy', 'noisy', 'quiet', 'crowded', 'spacious'],
            
            // Experience related
            'experience' => ['experience', 'visit', 'dining', 'meal', 'evening', 'night', 'celebration'],
            'recommendation' => ['recommend', 'return', 'again', 'never', 'always', 'favorite', 'best'],
            
            // Price related
            'price' => ['price', 'cost', 'expensive', 'cheap', 'affordable', 'worth', 'value', 'overpriced'],
            
            // Cleanliness
            'cleanliness' => ['clean', 'dirty', 'hygiene', 'sanitary', 'spotless', 'messy', 'tidy'],
            
            // Time
            'time' => ['wait', 'time', 'minutes', 'hours', 'reservation', 'booking', 'schedule'],
        ];
        
        // Extract words
        foreach ($wordCategories as $category => $words) {
            foreach ($words as $word) {
                if (strpos($textLower, $word) !== false) {
                    // Check for negation
                    if (!self::isNegated($textLower, $word)) {
                        $importantWords[] = $word;
                    }
                }
            }
        }
        
        return array_unique($importantWords);
    }

    /**
     * Utility: Get Sentiment Label from Score (aligned with reports)
     */
    public static function getSentimentLabel($score)
    {
        // Aligned with ReportController::getSentimentLabel
        if ($score === null) return 'Neutral';
        if ($score >= 0.7) return 'Positive';
        if ($score >= 0.4) return 'Neutral';
        return 'Negative';
    }
    
    /**
     * Utility: Get Sentiment Percentage for reports
     */
    public static function getSentimentPercentage($score)
    {
        if ($score === null) return 50;
        return round($score * 100);
    }
    
    /**
     * Utility: Get Sentiment Category for dashboard
     */
    public static function getSentimentCategory($score)
    {
        if ($score === null) return 'Neutral';
        if ($score >= 0.8) return 'Very Positive';
        if ($score >= 0.7) return 'Positive';
        if ($score >= 0.6) return 'Somewhat Positive';
        if ($score >= 0.5) return 'Neutral';
        if ($score >= 0.4) return 'Somewhat Negative';
        if ($score >= 0.3) return 'Negative';
        return 'Very Negative';
    }
    
    /**
     * Utility: Process Complete Review (with alignment to reports)
     */
    public static function processReview($text, $staff_id = null)
    {
        $sentimentScore = self::analyzeSentiment($text);
        $topics = self::extractTopics($text);
        
        return [
            'moderation' => self::aiModeration($text),
            'sentiment' => $sentimentScore,
            'sentiment_score' => $sentimentScore, // For backward compatibility
            'sentiment_label' => self::getSentimentLabel($sentimentScore),
            'sentiment_category' => self::getSentimentCategory($sentimentScore),
            'sentiment_percentage' => self::getSentimentPercentage($sentimentScore),
            'topics' => $topics,
            'staff_suggestions' => self::analyzeStaffPerformance($text, $staff_id, $sentimentScore),
            'recommendations' => self::generateRecommendations($topics, $sentimentScore),
            'emotion' => self::detectEmotion($text),
            'key_phrases' => self::extractKeyPhrases($text),
            // For report compatibility
            'ai_sentiment_score' => self::getSentimentPercentage($sentimentScore),
            'is_positive' => $sentimentScore >= 0.7,
            'is_negative' => $sentimentScore < 0.4,
            'is_neutral' => $sentimentScore >= 0.4 && $sentimentScore < 0.7
        ];
    }
    
    /**
     * Batch process reviews for report generation
     */
    public static function processReviewsBatch($reviews)
    {
        $results = [];
        
        foreach ($reviews as $review) {
            $text = $review->raw_text ?? $review->comment ?? '';
            $staff_id = $review->staff_id ?? null;
            
            if (!empty($text)) {
                $results[$review->id] = self::processReview($text, $staff_id);
            }
        }
        
        return $results;
    }
    
    /**
     * Calculate aggregated sentiment metrics for reports
     */
    public static function calculateAggregatedSentiment($reviews)
    {
        $total = count($reviews);
        $positive = 0;
        $neutral = 0;
        $negative = 0;
        $totalScore = 0;
        
        foreach ($reviews as $review) {
            $score = $review->sentiment_score ?? 0;
            $totalScore += $score;
            
            if ($score >= 0.7) {
                $positive++;
            } elseif ($score >= 0.4) {
                $neutral++;
            } else {
                $negative++;
            }
        }
        
        $avgScore = $total > 0 ? round($totalScore / $total, 2) : 0;
        
        return [
            'total_reviews' => $total,
            'positive_count' => $positive,
            'neutral_count' => $neutral,
            'negative_count' => $negative,
            'positive_percentage' => $total > 0 ? round(($positive / $total) * 100) : 0,
            'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100) : 0,
            'negative_percentage' => $total > 0 ? round(($negative / $total) * 100) : 0,
            'average_score' => $avgScore,
            'average_percentage' => round($avgScore * 100),
            'sentiment_label' => self::getSentimentLabel($avgScore)
        ];
    }
    
    /**
     * Extract common topics for reports
     */
    public static function extractCommonTopics($reviews, $limit = 5)
    {
        $topicCounts = [];
        
        foreach ($reviews as $review) {
            $topics = $review->topics ?? [];
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?? [];
            }
            
            foreach ($topics as $topic) {
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            }
        }
        
        arsort($topicCounts);
        return array_slice($topicCounts, 0, $limit, true);
    }
    
    /**
     * Generate AI insights summary for dashboard
     */
    public static function generateDashboardInsights($reviews)
    {
        $sentimentData = self::calculateAggregatedSentiment($reviews);
        $topTopics = self::extractCommonTopics($reviews, 3);
        
        $insights = [
            'summary' => '',
            'key_findings' => [],
            'recommendations' => []
        ];
        
        // Generate summary
        if ($sentimentData['total_reviews'] === 0) {
            $insights['summary'] = 'No reviews available for analysis.';
        } else {
            $summary = "Overall sentiment is ";
            
            if ($sentimentData['positive_percentage'] >= 70) {
                $summary .= "highly positive";
            } elseif ($sentimentData['positive_percentage'] >= 50) {
                $summary .= "generally positive";
            } elseif ($sentimentData['positive_percentage'] >= 30) {
                $summary .= "mixed";
            } else {
                $summary .= "predominantly negative";
            }
            
            $summary .= ", with {$sentimentData['positive_percentage']}% of reviews expressing positive sentiment. ";
            $summary .= "The average rating is {$sentimentData['average_score']} out of 5. ";
            
            if (!empty($topTopics)) {
                $topTopic = array_key_first($topTopics);
                $summary .= "A recurring topic mentioned is " . $topTopic . ". ";
            }
            
            $insights['summary'] = trim($summary);
        }
        
        // Key findings
        if ($sentimentData['positive_percentage'] >= 70) {
            $insights['key_findings'][] = 'Strong positive sentiment among customers';
        }
        
        if ($sentimentData['negative_percentage'] >= 30) {
            $insights['key_findings'][] = 'Significant negative feedback requires attention';
        }
        
        foreach ($topTopics as $topic => $count) {
            $insights['key_findings'][] = "Frequent mentions of: {$topic} ({$count} times)";
        }
        
        // Recommendations
        if ($sentimentData['negative_percentage'] >= 30) {
            $insights['recommendations'][] = 'Address negative feedback patterns immediately';
        }
        
        if ($sentimentData['positive_percentage'] >= 70) {
            $insights['recommendations'][] = 'Leverage positive feedback for marketing';
        }
        
        if (!empty($topTopics)) {
            $topTopic = array_key_first($topTopics);
            $insights['recommendations'][] = "Focus on improving: {$topTopic}";
        }
        
        return $insights;
    }
}