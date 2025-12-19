<?php

namespace App\Helpers;

use App\Models\ReviewNew;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use getID3;
use Carbon\Carbon;
class AIProcessor
{

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
     * Utility: Get Sentiment Label from Score (aligned with reports)
     */
    public static function getSentimentLabel(?float $score): string
    {
        if ($score === null) {
            return 'neutral';
        }
        return $score >= 0.7 ? 'positive' : ($score >= 0.4 ? 'neutral' : 'negative');
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
     * Utility: Get Sentiment Percentage for reports
     */
    public static function getSentimentPercentage($score)
    {
        if ($score === null) return 50;
        return round($score * 100);
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
    
     /**
     * Utility: Process Complete Review (with alignment to reports)
     */
    public static function processReview($text, $staff_id = null)
    {
        $moderation = self::aiModeration($text);
        $sentiment_score = self::analyzeSentiment($text);
        $topics = self::extractTopics($text);
        $sentiment_label = self::getSentimentLabel($sentiment_score);
        $sentiment_category = self::getSentimentCategory($sentiment_score);
        $sentiment_percentage = self::getSentimentPercentage($sentiment_score);
        $staff_suggestions = self::analyzeStaffPerformance($text, $staff_id, $sentiment_score);
        $recommendations = self::generateRecommendations($topics, $sentiment_score);
        $emotion = self::detectEmotion($text);
        $key_phrases = self::extractKeyPhrases($text);

        return [
            'moderation' => $moderation,
            'sentiment' => $sentiment_score,
            'sentiment_score' => $sentiment_score, // For backward compatibility
            'sentiment_label' => $sentiment_label,
            'sentiment_category' => $sentiment_category,
            'sentiment_percentage' => $sentiment_percentage,
            'topics' => $topics,
            'staff_suggestions' => $staff_suggestions,
            'recommendations' => $recommendations,
            'emotion' => $emotion,
            'key_phrases' => $key_phrases,
            // For report compatibility
            'ai_sentiment_score' => $sentiment_percentage,
            'is_positive' => $sentiment_score >= 0.7,
            'is_negative' => $sentiment_score < 0.4,
            'is_neutral' => $sentiment_score >= 0.4 && $sentiment_score < 0.7
        ];
    }


























    // ________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________-


    public static  function getTopMentionedStaff($positiveReviews)
    {
        $staffMentions = [];

        foreach ($positiveReviews as $review) {
            if ($review->staff_id) {
                $staffMentions[$review->staff_id] = ($staffMentions[$review->staff_id] ?? 0) + 1;
            }
        }

        if (empty($staffMentions)) {
            return [];
        }

        arsort($staffMentions);

        $result = [];
        foreach (array_slice($staffMentions, 0, 3) as $staffId => $count) {
            $staff = User::find($staffId);
            if ($staff) {
                $result[] = $staff->name . " ({$count})";
            }
        }

        return $result;
    }



   public static function getStaffPerformanceSnapshot($businessId, $dateRange)
    {
        
        // Get staff reviews WITH calculated rating
        $staffReviews = ReviewNew::with('staff')
            ->where('business_id', $businessId)
            ->globalFilters(0, $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->withCalculatedRating()
            ->get();

        $staffData = [];

        foreach ($staffReviews as $staffId => $reviews) {
            if ($reviews->count() < 3) continue; // Minimum reviews

            $staff = $reviews->first()->staff;
            if (!$staff) continue;

            // Calculate average rating FROM calculated_rating field
            $avgRating = $reviews->isNotEmpty()
                ? round($reviews->avg('calculated_rating'), 1)
                : 0;

            // Calculate sentiment metrics
            $positiveReviews = $reviews->where('calculated_rating', '>=', 4)->count();
            $negativeReviews = $reviews->where('calculated_rating', '<=', 2)->count();
            $neutralReviews = $reviews->whereBetween('calculated_rating', [2.1, 3.9])->count();

            $staff_suggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();

            $staffData[] = [
                'id' => $staffId,
                'name' => $staff->name,
                'email' => $staff->email,
                'job_title' => $staff->job_title ?? 'Staff',
                'rating' => $avgRating,
                'review_count' => $reviews->count(),
                'sentiment_breakdown' => [
                    'positive' => $reviews->count() > 0
                        ? round(($positiveReviews / $reviews->count()) * 100)
                        : 0,
                    'neutral' => $reviews->count() > 0
                        ? round(($neutralReviews / $reviews->count()) * 100)
                        : 0,
                    'negative' => $reviews->count() > 0
                        ? round(($negativeReviews / $reviews->count()) * 100)
                        : 0
                ],
                'positive_count' => $positiveReviews,
                'negative_count' => $negativeReviews,
                'skill_gaps' => self::extractSkillGapsFromSuggestions($staff_suggestions),
                'recommended_training' => $staff_suggestions->first() ?? 'General Training',
                'last_review_date' => $reviews->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                'rating_trend' => self::calculateStaffRatingTrend($reviews)
            ];
        }

        // Sort by rating (highest first)
        usort($staffData, fn($a, $b) => $b['rating'] <=> $a['rating']);

        $top = array_slice($staffData, 0, 3);
        $needsImprovement = array_slice(array_reverse($staffData), 0, 3);

        // Add overall stats
        $totalStaffWithReviews = count($staffData);
        $overallAvgRating = $totalStaffWithReviews > 0
            ? round(array_sum(array_column($staffData, 'rating')) / $totalStaffWithReviews, 1)
            : 0;

        return [
            'top_performing' => $top,
            'needs_improvement' => $needsImprovement,
            'overall_stats' => [
                'total_staff_with_reviews' => $totalStaffWithReviews,
                'overall_average_rating' => $overallAvgRating,
                'top_performer_rating' => !empty($top) ? $top[0]['rating'] : 0,
                'lowest_performer_rating' => !empty($needsImprovement)
                    ? $needsImprovement[0]['rating']
                    : 0,
                'rating_gap' => !empty($top) && !empty($needsImprovement)
                    ? round($top[0]['rating'] - $needsImprovement[0]['rating'], 1)
                    : 0
            ]
        ];
    }
public static function extractSkillGapsFromSuggestions($suggestions)
    {
        return $suggestions
            ->filter(fn($s) => stripos($s, 'needs') !== false || stripos($s, 'requires') !== false)
            ->map(fn($s) => preg_replace('/.*needs\s+(.*?) training.*/i', '$1', $s))
            ->filter(fn($s) => strlen($s) > 3)
            ->values()
            ->toArray();
    }
  public static   function extractOpportunitiesFromSuggestions($suggestions)
    {
        return collect($suggestions)
            ->filter(fn($s) => stripos($s, 'add') !== false || stripos($s, 'highlight') !== false)
            ->take(2)
            ->values()
            ->toArray();
    }
   public static function generatePredictions($reviews)
    {
        // Calculate average rating from calculated_rating field (much faster)
        if ($reviews->isEmpty()) {
            return [[
                'prediction' => 'No reviews available for prediction.',
                'estimated_impact' => 'N/A'
            ]];
        }

        // Use calculated_rating directly from the query results
        $avgRating = $reviews->avg('calculated_rating') ?? 0;
        $predictedIncrease = max(0, 5 - $avgRating) * 0.05;

        return [[
            'prediction' => 'Improving identified issues could boost overall rating.',
            'estimated_impact' => '+' . round($predictedIncrease, 2) . ' points',
            'current_avg_rating' => round($avgRating, 1),
            'potential_new_rating' => round(min(5, $avgRating + $predictedIncrease), 1)
        ]];
    }
  public static  function transcribeAudio($filePath)
    {
        try {
            $api_key = env('HF_API_KEY');
            $audio = file_get_contents($filePath);

            // Log file basic info
            \Log::info("HF Transcription Started", [
                'file_path' => $filePath,
                'file_size' => strlen($audio),
                'mime' => mime_content_type($filePath)
            ]);

            $ch = curl_init("https://router.huggingface.co/hf-inference/models/openai/whisper-large-v3");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $api_key",
                    "Content-Type: audio/mpeg"
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $audio,
                CURLOPT_RETURNTRANSFER => true,
            ]);

            $result = curl_exec($ch);
            $error  = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log full CURL response
            \Log::info("HF Whisper API Response", [
                'http_status' => $status,
                'curl_error'  => $error,
                'raw_result'  => $result
            ]);

            if ($error) {
                \Log::error("HF Whisper CURL Error: $error");
                return '';
            }

            $data = json_decode($result, true);

            // Log decoded output
            \Log::info("HF Whisper Decoded Response", [
                'decoded' => $data
            ]);

            return $data['text'] ?? '';
        } catch (\Exception $e) {
            \Log::error("transcribeAudio() exception: " . $e->getMessage());
            return '';
        }
    }

  public static  function getAiInsightsPanel($businessId, $dateRange)
    {
        // Get reviews WITH calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('ai_suggestions')
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();

        // Extract common themes from existing AI suggestions
        $allSuggestions = $reviews->pluck('ai_suggestions')->flatten();
        $allTopics = $reviews->pluck('topics')->flatten();

        return [
            'summary' => self::generateAiSummary($reviews),
            'detected_issues' => self::extractIssuesFromSuggestions($allSuggestions),
            'opportunities' => self::extractOpportunitiesFromSuggestions($allSuggestions),
            'predictions' => self::generatePredictions($reviews)
        ];
    }

    /**
     * Get branch comparison data with real metrics
     */
   public static function getBranchComparisonData($branch, $startDate, $endDate)
    {
        $businessId = $branch->business_id;

        // Get reviews with calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branch->id)
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->get();

           
        $totalReviews = $reviews->count();

        // Average rating from calculated_rating
        $averageRating = $reviews->avg('calculated_rating') ?? 0;
        

        // AI Sentiment Score
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $aiSentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

        // CSAT Score
        $csatCount = $reviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) >= 4;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        // Staff performance metrics
        $staffPerformance = self::getBranchStaffPerformance($branch->id, $businessId, $startDate, $endDate);
    

        // Top topics
        $topTopics = self::extractBranchTopics($reviews);

        return [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code ?? 'BRN-' . str_pad($branch->id, 5, '0', STR_PAD_LEFT),
                'location' => $branch->location,
                'manager_name' => $branch->manager ? $branch->manager->name : 'Not assigned',
                'business_name' => $branch->business ? $branch->business->name : 'Unknown'
            ],
            'metrics' => [
                'total_reviews' => $totalReviews,
                'average_rating' => round($averageRating, 1),
                'ai_sentiment_score' => $aiSentimentScore,
                'csat_score' => $csatScore,
                'response_rate' => calculateResponseRate($reviews)
            ],
            'staff_performance' => $staffPerformance,
            'top_topics' => array_slice($topTopics, 0, 5)
        ];
    }

    /**
     * Get branch staff performance
     */
public static function getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate)
{
    // First get the reviews with calculated rating
    $staffReviews = ReviewNew::where('business_id', $businessId)
        ->where('branch_id', $branchId)
        ->globalFilters(0, $businessId, 1)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->withCalculatedRating()
        ->get();

    // Manual grouping since calculated_rating is not a real database column
    $groupedReviews = [];
    foreach ($staffReviews as $review) {
        if ($review->staff_id) {
            $groupedReviews[$review->staff_id][] = $review;
        }
    }
    
    $staffPerformance = [];

    foreach ($groupedReviews as $staffId => $reviews) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        // Manual calculations
        $totalRating = 0;
        $reviewCount = count($reviews);
        $positiveCount = 0;
        $latestReviewDate = null;
        
        foreach ($reviews as $review) {
            $totalRating += $review->calculated_rating ?? 0;
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveCount++;
            }
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
        }
        
        $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;

        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'avg_rating' => round($avgRating, 1),
            'reviews_count' => $reviewCount,
            'positive_percentage' => $reviewCount > 0 ? round(($positiveCount / $reviewCount) * 100) : 0,
            'last_review_date' => $latestReviewDate 
                ? $latestReviewDate->diffForHumans() 
                : 'No reviews'
        ];
    }

    // Sort by average rating descending
    usort($staffPerformance, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return array_slice($staffPerformance, 0, 3);
}

  public static  function extractBranchTopics($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            // Use stored topics if available
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment
            if ($review->comment) {
                $commonTopics = ['service', 'staff', 'wait', 'quality', 'price', 'clean', 'product', 'location'];
                $comment = strtolower($review->comment);

                foreach ($commonTopics as $topic) {
                    if (strpos($comment, $topic) !== false) {
                        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($topicCounts);
        return $topicCounts;
    }

      /**
     * Generate AI insights for branch comparison
     */
   public static function generateBranchComparisonInsights($branchesData, $allMetrics)
    {
        if (count($branchesData) === 0) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        // Find best performing branch by rating
        $bestBranch = null;
        $bestRating = 0;
        $mostReviews = 0;
        $mostReviewsBranch = null;

        foreach ($branchesData as $branchData) {
            $rating = $branchData['metrics']['average_rating'];
            $reviews = $branchData['metrics']['total_reviews'];

            if ($rating > $bestRating) {
                $bestRating = $rating;
                $bestBranch = $branchData['branch']['name'];
            }

            if ($reviews > $mostReviews) {
                $mostReviews = $reviews;
                $mostReviewsBranch = $branchData['branch']['name'];
            }
        }

        // Find worst performing branch by rating
        $worstBranch = null;
        $worstRating = 5;
        foreach ($branchesData as $branchData) {
            $rating = $branchData['metrics']['average_rating'];
            if ($rating < $worstRating && $branchData['metrics']['total_reviews'] > 0) {
                $worstRating = $rating;
                $worstBranch = $branchData['branch']['name'];
            }
        }

        // Generate overview
        $overview = "The {$bestBranch} branch consistently outperforms others in Average Rating ({$bestRating}) ";
        $overview .= "and CSAT ({$branchesData[array_search($bestBranch, array_column($branchesData, 'branch'))]['metrics']['csat_score']}%), ";
        $overview .= "driven by positive feedback on staff performance. ";

        if ($mostReviewsBranch !== $bestBranch) {
            $overview .= "The {$mostReviewsBranch} branch has the highest volume of reviews, ";
            $overview .= "indicating high traffic, but its sentiment score is slightly lower. ";
        }

        if ($worstBranch) {
            $overview .= "{$worstBranch} lags in all key metrics, suggesting a need for operational review, ";
            $overview .= "particularly in areas affecting customer sentiment.";
        }

        $keyFindings = [
            "Highest rating: {$bestBranch} ({$bestRating})",
            "Most reviews: {$mostReviewsBranch} ({$mostReviews})"
        ];

        if ($worstBranch) {
            $keyFindings[] = "Needs improvement: {$worstBranch}";
        }

        return [
            'overview' => $overview,
            'key_findings' => $keyFindings
        ];
    }
     /**
     * Generate comparison highlights table
     */
   public static function generateComparisonHighlights($branchesData)
    {
        if (count($branchesData) < 2) {
            return [];
        }

        $highlights = [];

        // CSAT comparison
        $bestCsat = 0;
        $bestCsatBranch = '';
        $worstCsat = 100;
        $worstCsatBranch = '';

        foreach ($branchesData as $branchData) {
            $csat = $branchData['metrics']['csat_score'];
            $branchName = $branchData['branch']['name'];

            if ($csat > $bestCsat) {
                $bestCsat = $csat;
                $bestCsatBranch = $branchName;
            }

            if ($csat < $worstCsat && $branchData['metrics']['total_reviews'] > 0) {
                $worstCsat = $csat;
                $worstCsatBranch = $branchName;
            }
        }

        $highlights[] = [
            'category' => 'CSAT',
            'best_branch' => $bestCsatBranch,
            'best_value' => "{$bestCsat}%",
            'worst_branch' => $worstCsatBranch,
            'worst_value' => "{$worstCsat}%"
        ];

        // Staff Performance complaints
        $mostComplaints = 0;
        $mostComplaintsBranch = '';
        $leastComplaints = PHP_INT_MAX;
        $leastComplaintsBranch = '';

        foreach ($branchesData as $branchData) {
            $totalReviews = $branchData['metrics']['total_reviews'];
            if ($totalReviews === 0) continue;

            // Calculate complaints percentage (negative sentiment reviews)
            $negativeReviews = 0;
            foreach ($branchData['staff_performance'] as $staff) {
                $negativeReviews += (100 - $staff['positive_percentage']) * $staff['reviews_count'] / 100;
            }
            $complaintPercentage = $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0;
            $branchName = $branchData['branch']['name'];

            if ($complaintPercentage > $mostComplaints) {
                $mostComplaints = $complaintPercentage;
                $mostComplaintsBranch = $branchName;
            }

            if ($complaintPercentage < $leastComplaints) {
                $leastComplaints = $complaintPercentage;
                $leastComplaintsBranch = $branchName;
            }
        }

        $highlights[] = [
            'category' => 'Staff Performance',
            'most_complaints' => $mostComplaintsBranch,
            'least_complaints' => $leastComplaintsBranch
        ];

        return $highlights;
    }

    /**
     * Get sentiment trend over time for chart
     */
   public static function getSentimentTrendOverTime($branches, $startDate, $endDate)
    {
        // Group by month for the trend
        $months = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $months[] = $current->format('M Y');
            $current->addMonth();
        }

        $trendData = [];

        foreach ($branches as $branch) {
            $branchTrend = [];

            $current = $startDate->copy();
            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();

                $reviews = ReviewNew::where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->globalFilters(0, $branch->business_id)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->withCalculatedRating()
                    ->get();

                $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
                $totalReviews = $reviews->count();
                $sentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

                $branchTrend[] = $sentimentScore;
                $current->addMonth();
            }

            $trendData[] = [
                'branch_name' => $branch->name,
                'data' => $branchTrend
            ];
        }

        return [
            'months' => $months,
            'trends' => $trendData
        ];
    }

    /**
     * Get staff complaints by branch
     */
   public static function getStaffComplaintsByBranch($branches, $startDate, $endDate)
    {
        $complaintsByBranch = [];

        foreach ($branches as $branch) {
            $reviews = ReviewNew::where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->globalFilters(0, $branch->business_id, 1)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCalculatedRating()
                ->get();

            $negativeReviews = $reviews->where('sentiment_score', '<', 0.4)->count();
            $totalReviews = $reviews->count();

            $complaintsByBranch[] = [
                'branch_name' => $branch->name,
                'complaints_count' => $negativeReviews,
                'total_reviews' => $totalReviews,
                'complaint_percentage' => $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0
            ];
        }

        // Sort by complaint percentage descending
        usort($complaintsByBranch, function ($a, $b) {
            return $b['complaint_percentage'] <=> $a['complaint_percentage'];
        });

        return $complaintsByBranch;
    }

     /**
     * Calculate branch summary metrics
     */
   public static function calculateBranchSummary($reviews)
    {
        $totalReviews = $reviews->count();

        // Use calculated_rating instead of separate rating calculation
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        // AI Sentiment
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $sentiment = 'Neutral';

        if ($totalReviews > 0) {
            $positivePercentage = ($positiveReviews / $totalReviews) * 100;
            if ($positivePercentage >= 70) {
                $sentiment = 'Positive';
            } elseif ($positivePercentage <= 30) {
                $sentiment = 'Negative';
            }
        }

        // CSAT Score (percentage of 4-5 star ratings)
        $csatCount = $reviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) >= 4;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        // Top Topic (from review topics or extract from comments)
        $topTopic = self::extractTopTopic($reviews);

        // Flagged reviews
        $flagged = $reviews->where('status', 'flagged')->count();

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => round($averageRating, 1),
            'rating_out_of' => 5,
            'ai_sentiment' => $sentiment,
            'csat_score' => $csatScore,
            'top_topic' => $topTopic['topic'] ?? 'General',
            'flagged' => $flagged,
            'response_rate' => calculateResponseRate($reviews)
        ];
    }
   public static  function extractTopTopic($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            // Use stored topics if available
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment
            if ($review->comment) {
                $commonTopics = ['service', 'staff', 'wait', 'quality', 'price', 'clean', 'product', 'location'];
                $comment = strtolower($review->comment);

                foreach ($commonTopics as $topic) {
                    if (strpos($comment, $topic) !== false) {
                        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                    }
                }
            }
        }

        if (empty($topicCounts)) {
            return ['topic' => 'General', 'count' => 0];
        }

        arsort($topicCounts);
        $topTopic = array_key_first($topicCounts);

        return [
            'topic' => ucfirst($topTopic),
            'count' => $topicCounts[$topTopic],
            'percentage' => $reviews->count() > 0 ? round(($topicCounts[$topTopic] / $reviews->count()) * 100, 1) : 0
        ];
    }
   public static function generateAiInsights($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                'summary' => 'No reviews available for analysis.',
                'sentiment_breakdown' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0
                ]
            ];
        }

        $totalReviews = $reviews->count();

        // Sentiment breakdown
        $positive = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negative = $reviews->where('sentiment_score', '<', 0.4)->count();

        $sentimentBreakdown = [
            'positive' => round(($positive / $totalReviews) * 100),
            'neutral' => round(($neutral / $totalReviews) * 100),
            'negative' => round(($negative / $totalReviews) * 100)
        ];

        // Generate summary
        $summary = self::generateAiSummaryReport($reviews, $sentimentBreakdown);

        return [
            'summary' => $summary,
            'sentiment_breakdown' => $sentimentBreakdown,
            'key_trends' => self::extractKeyTrends($reviews)
        ];
    }
   public static function generateAiSummaryReport($reviews, $sentimentBreakdown)
    {
        $totalReviews = $reviews->count();
        $positivePercentage = $sentimentBreakdown['positive'];

        $summary = "Overall sentiment is ";

        if ($positivePercentage >= 70) {
            $summary .= "highly positive";
        } elseif ($positivePercentage >= 50) {
            $summary .= "generally positive";
        } elseif ($positivePercentage >= 30) {
            $summary .= "mixed";
        } else {
            $summary .= "predominantly negative";
        }

        $summary .= ", with {$positivePercentage}% of reviews expressing positive sentiment. ";

        // Calculate average rating
        $avgRating = $reviews->avg('calculated_rating') ?? 0;
        $summary .= "The average rating is " . round($avgRating, 1) . " out of 5. ";

        // Check for common issues
        $commonIssues = self::findCommonIssues($reviews);
        if (!empty($commonIssues)) {
            $summary .= "A recurring issue mentioned is " . $commonIssues[0]['topic'] . ". ";
        }

        // Check for peak times if available
        $peakTimes = self::findPeakReviewTimes($reviews);
        if ($peakTimes) {
            $summary .= "Peak feedback times are around {$peakTimes}. ";
        }

        return trim($summary);
    }
     /**
     * Extract key trends from reviews
     */
   public static function extractKeyTrends($reviews)
    {
        $trends = [];

        if ($reviews->isEmpty()) {
            return $trends;
        }

        // Check for improving/declining sentiment over time
        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstSentiment = $firstHalf->avg('sentiment_score');
        $secondSentiment = $secondHalf->avg('sentiment_score');

        if ($secondSentiment > $firstSentiment + 0.1) {
            $trends[] = 'Improving sentiment trend';
        } elseif ($secondSentiment < $firstSentiment - 0.1) {
            $trends[] = 'Declining sentiment trend';
        }

        // Check for specific issue trends
        $commonIssues = self::findCommonIssues($reviews);
        foreach ($commonIssues as $issue) {
            if ($issue['count'] >= 5) {
                $trends[] = "Frequent mentions of " . $issue['topic'];
            }
        }

        return array_slice($trends, 0, 3);
    }
     /**
     * Find common issues in reviews
     */
   public static function findCommonIssues($reviews)
    {
        $issues = [
            'Wait Time' => [
                'keywords' => ['wait', 'queue', 'line', 'slow', 'long', 'minutes', 'delay', 'time', 'late', 'patient', 'standing'],
                'description' => 'Customers mentioned longer than expected wait times'
            ],
            'Service Quality' => [
                'keywords' => ['rude', 'unhelpful', 'ignore', 'attitude', 'unprofessional', 'careless', 'inattentive', 'poor service'],
                'description' => 'Service quality needs improvement'
            ],
            'Cleanliness' => [
                'keywords' => ['dirty', 'messy', 'filthy', 'clean', 'hygiene', 'sanitary', 'untidy', 'stain', 'smell', 'wipe'],
                'description' => 'Cleanliness and maintenance concerns'
            ],
            'Pricing' => [
                'keywords' => ['expensive', 'pricey', 'overpriced', 'cost', 'value', 'worth', 'cheap', 'affordable', 'budget'],
                'description' => 'Pricing or value for money concerns'
            ],
            'Food Quality' => [
                'keywords' => ['taste', 'flavor', 'fresh', 'stale', 'cold', 'hot', 'cooked', 'raw', 'quality', 'bland', 'dry'],
                'description' => 'Food or product quality issues'
            ],
            'Ambiance' => [
                'keywords' => ['noisy', 'loud', 'quiet', 'atmosphere', 'music', 'lighting', 'crowded', 'small', 'uncomfortable'],
                'description' => 'Ambiance or environment feedback'
            ]
        ];

        $results = [];

        foreach ($reviews as $review) {
            if (empty($review->comment)) continue;

            $comment = strtolower(trim($review->comment));

            foreach ($issues as $topic => $data) {
                foreach ($data['keywords'] as $keyword) {
                    if (strpos($comment, $keyword) !== false) {
                        // Initialize if not exists
                        if (!isset($results[$topic])) {
                            $results[$topic] = [
                                'topic' => $topic,
                                'count' => 0,
                                'description' => $data['description'],
                                'keyword_matches' => []
                            ];
                        }

                        $results[$topic]['count']++;
                        if (!in_array($keyword, $results[$topic]['keyword_matches'])) {
                            $results[$topic]['keyword_matches'][] = $keyword;
                        }
                        break; // Count once per topic per review
                    }
                }
            }
        }

        // Convert to array and sort by count
        $sortedResults = array_values($results);
        usort($sortedResults, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $sortedResults;
    }
     /**
     * Find peak review times
     */
  public static  function findPeakReviewTimes($reviews)
    {
        if ($reviews->isEmpty()) return null;

        $hourlyCounts = array_fill(0, 24, 0);

        foreach ($reviews as $review) {
            $hour = $review->created_at->hour;
            $hourlyCounts[$hour]++;
        }

        $peakHour = array_search(max($hourlyCounts), $hourlyCounts);

        return sprintf('%02d:00', $peakHour);
    }
    /**
     * Generate recommendations based on review analysis
     */
   public static function generateBranchRecommendations($reviews)
    {
        $recommendations = [];
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                [
                    'type' => 'Info',
                    'title' => 'No Data Available',
                    'description' => 'No reviews found for this period. Encourage customers to provide feedback.'
                ]
            ];
        }

        // Track why recommendations might be empty
        $debugInfo = [
            'total_reviews' => $totalReviews,
            'positive_reviews' => 0,
            'has_comments' => 0,
            'staff_praise_count' => 0,
            'issues_found' => 0
        ];

        // 1. Identify strengths (positive reviews with specific praise)
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7);
        $debugInfo['positive_reviews'] = $positiveReviews->count();

        // Check how many reviews have comments
        $reviewsWithComments = $reviews->filter(function ($review) {
            return !empty(trim($review->comment ?? ''));
        });
        $debugInfo['has_comments'] = $reviewsWithComments->count();

        // Enhanced staff praise detection
        $staffPraise = $positiveReviews->filter(function ($review) {
            if (empty($review->comment)) return false;

            $text = strtolower(trim($review->comment));

            // Comprehensive staff and service keywords
            $staffKeywords = [
                // Staff roles
                'staff',
                'employee',
                'waiter',
                'waitress',
                'server',
                'host',
                'hostess',
                'bartender',
                'chef',
                'cook',
                'manager',
                'crew',
                'team',
                'personnel',
                'assistant',
                'attendant',
                'rep',
                'representative',
                'agent',
                'worker',
                'cashier',
                'receptionist',
                'front desk',
                'service',
                'person',

                // Positive service attributes
                'friendly',
                'helpful',
                'knowledgeable',
                'professional',
                'expert',
                'courteous',
                'polite',
                'respectful',
                'welcoming',
                'warm',
                'attentive',
                'caring',
                'thoughtful',
                'considerate',
                'efficient',
                'quick',
                'fast',
                'prompt',
                'timely',
                'smile',
                'smiling',
                'kind',
                'nice',
                'great',
                'excellent',
                'outstanding',
                'amazing',
                'fantastic',
                'wonderful',
                'patient',
                'understanding',
                'accommodating',
                'recommend',
                'suggest',
                'advise',
                'explain',
                'solve',
                'resolve',
                'fix',
                'handle',
                'manage',
                'go above',
                'above and beyond',
                'extra mile'
            ];

            // Check for any staff keyword
            foreach ($staffKeywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    // Quick check for obvious negations
                    if (
                        strpos($text, "not $keyword") !== false ||
                        strpos($text, "no $keyword") !== false ||
                        strpos($text, "never $keyword") !== false
                    ) {
                        continue;
                    }
                    return true;
                }
            }

            return false;
        });

        $debugInfo['staff_praise_count'] = $staffPraise->count();

        if ($staffPraise->count() >= 2) {
            // Get top mentioned staff and include in description
            $staffMentions = self::getTopMentionedStaff($staffPraise);
            $staffDescription = $staffMentions ?
                ' Top performing staff: ' . implode(', ', array_slice($staffMentions, 0, 2)) . '.' :
                '';

            $recommendations[] = [
                'type' => 'Strength',
                'title' => 'Staff Excellence',
                'description' => 'Customers appreciate your staff\'s service and professionalism.' . $staffDescription,
                'evidence_count' => $staffPraise->count(),
                'priority' => 'low'
            ];
        }

        // 2. Identify common issues
        $issues = self::findCommonIssues($reviews);
        $debugInfo['issues_found'] = count($issues);

        foreach ($issues as $issue) {
            if ($issue['count'] >= 2 && count($recommendations) < 3) {
                $recommendations[] = [
                    'type' => 'Weak Area',
                    'title' => $issue['topic'],
                    'description' => $issue['description'] . " (mentioned {$issue['count']} times)",
                    'evidence_count' => $issue['count'],
                    'priority' => $issue['count'] >= 4 ? 'high' : 'medium'
                ];

                // Add action item for this issue
                $action = self::generateActionItem($issue['topic'], $issue['count']);
                if ($action && count($recommendations) < 3) {
                    $recommendations[] = $action;
                }
            }
        }

        // 3. If no recommendations found, provide debug info
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'Info',
                'title' => 'Insufficient Feedback Data',
                'description' => 'Not enough specific feedback to generate recommendations. ' .
                    "Total reviews: {$debugInfo['total_reviews']}, " .
                    "With comments: {$debugInfo['has_comments']}, " .
                    "Positive reviews: {$debugInfo['positive_reviews']}, " .
                    "Staff praise mentions: {$debugInfo['staff_praise_count']}, " .
                    "Issues detected: {$debugInfo['issues_found']}",
                'debug_info' => $debugInfo
            ];
        }

        // Limit to 3 recommendations max
        return array_slice($recommendations, 0, 3);
    }
     /**
     * Get recent reviews for display
     */
  public static  function getRecentReviews($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $rating = $review->calculated_rating ?? $review->rate;

                return [
                    'id' => $review->id,
                    'rating' => $rating,
                    'stars' => str_repeat('★', floor($rating)) . str_repeat('☆', 5 - floor($rating)),
                    'review_text' => $review->comment ?? $review->raw_text ?? 'No comment',
                    'staff_name' => $review->staff ? $review->staff->name : 'Not assigned',
                    'staff_id' => $review->staff_id,
                    'sentiment' => self::getSentimentLabel($review->sentiment_score),
                    'date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('Y-m-d H:i:s'),
                    'is_flagged' => $review->status === 'flagged',
                    'has_actions' => true,
                    'user_type' => $review->user_id ? 'Registered' : ($review->guest_id ? 'Guest' : 'Anonymous')
                ];
            })
            ->values()
            ->toArray();
    }
     /**
     * Get staff performance data
     */
 public static function getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 5)
{
    // Get reviews with staff assigned AND calculated rating in one query
    $staffReviews = ReviewNew::where('business_id', $businessId)
        ->where('branch_id', $branchId)
        ->globalFilters(0, $businessId, 1)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->withCalculatedRating()
        ->get();

    $staffPerformance = [];
    
    // Group reviews by staff_id manually since we can't use group By with eager loading
    $groupedReviews = [];
    foreach ($staffReviews as $review) {
        if ($review->staff_id) {
            $groupedReviews[$review->staff_id][] = $review;
        }
    }

    foreach ($groupedReviews as $staffId => $reviews) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        // Calculate average manually from the reviews collection
        $totalRating = 0;
        $reviewCount = count($reviews);
        $positiveReviews = 0;
        $latestReviewDate = null;
        
        foreach ($reviews as $review) {
            $totalRating += $review->calculated_rating ?? 0;
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveReviews++;
            }
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
        }
        
        $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;

        // Skip staff with very few reviews
        if ($reviewCount < 3) continue;

        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'staff_code' => $staff->employee_code ?? 'EMP-' . $staffId,
            'avg_rating' => round($avgRating, 1),
            'rating_out_of' => 5,
            'reviews_count' => $reviewCount,
            'ai_evaluation' => self::getStaffEvaluation($avgRating, $reviewCount),
            'has_profile' => true,
            'positive_percentage' => $reviewCount > 0 ? round(($positiveReviews / $reviewCount) * 100) : 0,
            'last_review_date' => $latestReviewDate ? $latestReviewDate->diffForHumans() : 'Never'
        ];
    }

    // Sort by average rating descending
    usort($staffPerformance, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return array_slice($staffPerformance, 0, $limit);
}
 public static    function getStaffEvaluation($avgRating, $reviewCount)
    {
        if ($reviewCount < 3) return 'Insufficient Data';
        if ($avgRating >= 4.5) return 'Top Performer';
        if ($avgRating >= 4.0) return 'Excellent';
        if ($avgRating >= 3.5) return 'Good';
        if ($avgRating >= 3.0) return 'Consistent';
        if ($avgRating >= 2.0) return 'Needs Improvement';
        return 'Critical Attention';
    }

    /**
     * Generate action item based on issue
     */
   public static function generateActionItem($issue, $evidenceCount)
    {
        $actions = [
            'Wait Time' => [
                'title' => 'Optimize Service Flow',
                'description' => 'Review staffing schedules during peak hours and implement queue management.',
                'priority' => $evidenceCount >= 4 ? 'high' : 'medium'
            ],
            'Service Quality' => [
                'title' => 'Service Training',
                'description' => 'Provide customer service training focusing on communication and attentiveness.',
                'priority' => 'medium'
            ],
            'Cleanliness' => [
                'title' => 'Cleanliness Protocol',
                'description' => 'Establish regular cleaning schedules and quality checks.',
                'priority' => 'medium'
            ],
            'Pricing' => [
                'title' => 'Value Assessment',
                'description' => 'Review pricing strategy and ensure clear value communication.',
                'priority' => 'low'
            ],
            'Food Quality' => [
                'title' => 'Quality Control',
                'description' => 'Implement stricter quality checks and preparation standards.',
                'priority' => 'high'
            ],
            'Ambiance' => [
                'title' => 'Environment Improvement',
                'description' => 'Assess and improve lighting, noise levels, and seating comfort.',
                'priority' => 'low'
            ]
        ];

        if (isset($actions[$issue])) {
            return [
                'type' => 'Action',
                'title' => $actions[$issue]['title'],
                'description' => $actions[$issue]['description'],
                'priority' => $actions[$issue]['priority']
            ];
        }

        return null;
    }

    public static  function calculateStaffRatingTrend($reviews)
    {
        if ($reviews->count() < 4) {
            return 'insufficient_data';
        }

        // Split reviews into two halves to see trend
        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstHalfAvg = $firstHalf->avg('calculated_rating') ?? 0;
        $secondHalfAvg = $secondHalf->avg('calculated_rating') ?? 0;

        if ($secondHalfAvg > $firstHalfAvg + 0.2) {
            return 'improving';
        } elseif ($secondHalfAvg < $firstHalfAvg - 0.2) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
   
   public static function emptyStaffMetrics($staffUser)
    {
        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => 0,
            'avg_rating' => 0,
            'sentiment_breakdown' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0
            ],
            'performance_by_category' => [],
            'top_topics' => [],
            'notable_reviews' => []
        ];
    }

  public static  function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return self::emptyStaffMetrics($staffUser);
        }

        // Calculate average rating from calculated_rating field
        $avgRating = $reviews->avg('calculated_rating') ?? 0;

        // Calculate sentiment distribution
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        $positivePercentage = round(($positiveCount / $totalReviews) * 100);
        $neutralPercentage = round(($neutralCount / $totalReviews) * 100);
        $negativePercentage = round(($negativeCount / $totalReviews) * 100);

        // Extract topics and categories
        $topics = self::extractTopicsFromReviews($reviews);
        $performanceByCategory = self::calculatePerformanceByCategory($reviews);
        $notableReviews = self::getNotableReviews($reviews);

        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => $totalReviews,
            'avg_rating' => round($avgRating, 1),
            'sentiment_breakdown' => [
                'positive' => $positivePercentage,
                'neutral' => $neutralPercentage,
                'negative' => $negativePercentage
            ],
            'performance_by_category' => $performanceByCategory,
            'top_topics' => array_slice($topics, 0, 5),
            'notable_reviews' => $notableReviews
        ];
    }
   public static  function extractTopicsFromReviews($reviews)
    {
        $allTopics = [];

        foreach ($reviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }
  public static  function calculatePerformanceByCategory($reviews)
    {
        $categories = [
            'friendliness' => ['friendly', 'polite', 'rude', 'attitude', 'nice'],
            'efficiency' => ['slow', 'fast', 'efficient', 'wait', 'time'],
            'knowledge' => ['knowledge', 'explain', 'information', 'helpful', 'expert']
        ];

        $performance = [];

        foreach ($categories as $category => $keywords) {
            $categoryReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($categoryReviews->count() > 0) {
                $avgSentiment = $categoryReviews->avg('sentiment_score');
                $performance[$category] = [
                    'score' => round($avgSentiment * 100),
                    'review_count' => $categoryReviews->count()
                ];
            } else {
                $performance[$category] = [
                    'score' => 0,
                    'review_count' => 0
                ];
            }
        }

        return $performance;
    }
   public static  function getNotableReviews($reviews, $limit = 2)
    {
        return $reviews->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                return [
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans()
                ];
            })
            ->values()
            ->toArray();
    }
   public static function getSentimentGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A has more positive reviews";
        } elseif ($gap < 0) {
            return "Staff B has more positive reviews";
        } else {
            return "Both have similar positive sentiment";
        }
    }
  public static   function getPreviousPeriodReviews($businessId, $period)
    {
        $startDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->startOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->startOfQuarter(),
            default => Carbon::now()->subMonth()->startOfMonth() // last_month
        };

        $endDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->endOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->endOfQuarter(),
            default => Carbon::now()->subMonth()->endOfMonth()
        };

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereNotNull('sentiment_score')

            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)

            ->withCalculatedRating()

            ->get();
    }
 public static    function calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews)
    {
        // Calculate current period average rating from calculated_rating field
        $currentAvgRating = $currentReviews->isNotEmpty()
            ? round($currentReviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate previous period average rating from calculated_rating field
        $previousAvgRating = $previousReviews->isNotEmpty()
            ? round($previousReviews->avg('calculated_rating'), 1)
            : 0;

        $currentSentiment = self::calculateAverageSentiment($currentReviews);
        $currentTotalReviews = $currentReviews->count();

        $previousSentiment = self::calculateAverageSentiment($previousReviews);
        $previousTotalReviews = $previousReviews->count();

        $ratingChange = $previousAvgRating > 0 ?
            round((($currentAvgRating - $previousAvgRating) / $previousAvgRating) * 100, 1) : 0;

        $sentimentChange = $previousSentiment > 0 ?
            round($currentSentiment - $previousSentiment, 1) : 0;

        $reviewsChange = $previousTotalReviews > 0 ?
            $currentTotalReviews - $previousTotalReviews : $currentTotalReviews;

        return [
            'overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $ratingChange,
                'change_type' => $ratingChange >= 0 ? 'positive' : 'negative'
            ],
            'overall_sentiment' => [
                'value' => $currentSentiment,
                'change' => $sentimentChange,
                'change_type' => $sentimentChange >= 0 ? 'positive' : 'negative'
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'change' => $reviewsChange,
                'change_type' => $reviewsChange >= 0 ? 'positive' : 'negative'
            ]
        ];
    }
   public static function calculateAverageSentiment($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        return round(($positiveReviews / $reviews->count()) * 100);
    }
  public static  function extractStaffTopics($staffReviews)
    {
        $allTopics = [];

        foreach ($staffReviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment if no topics set
            if (empty($review->topics) && $review->comment) {
                $commonWords = ['service', 'friendly', 'helpful', 'knowledge', 'slow', 'fast', 'polite', 'rude'];
                $comment = strtolower($review->comment);

                foreach ($commonWords as $word) {
                    if (strpos($comment, $word) !== false) {
                        $allTopics[$word] = ($allTopics[$word] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }
     /**
     * Get top three staff based on ratings and review count
     */
   public static function getTopThreeStaff($businessId, $filters = [])
{
    // Get reviews for the business with staff AND calculated rating
    $reviewsQuery = ReviewNew::where('business_id', $businessId)
        ->whereNotNull('staff_id')
        ->withCalculatedRating();

    // Apply the same filters as main query
    $reviewsQuery = applyFilters($reviewsQuery, $filters);

    // Add calculated rating to the query
    $reviews = $reviewsQuery->get();

    if ($reviews->isEmpty()) {
        return [
            'message' => 'No staff reviews found',
            'staff' => []
        ];
    }

    // Manual grouping by staff_id
    $staffGroups = [];
    foreach ($reviews as $review) {
        if ($review->staff_id) {
            $staffGroups[$review->staff_id][] = $review;
        }
    }

    $staffPerformance = [];
    
    foreach ($staffGroups as $staffId => $reviewsArray) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        $totalRating = 0;
        $totalReviews = count($reviewsArray);
        $positiveCount = 0;
        $latestReviewDate = null;
        $allTopics = [];
        
        foreach ($reviewsArray as $review) {
            // Calculate average rating
            $totalRating += $review->calculated_rating ?? 0;
            
            // Count positive reviews
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveCount++;
            }
            
            // Track latest review
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
            
            // Collect topics if they exist
            if (!empty($review->topics) && is_array($review->topics)) {
                $allTopics = array_merge($allTopics, $review->topics);
            }
        }
        
        // Calculate averages
        $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
        $sentimentPercentage = $totalReviews > 0 ? round(($positiveCount / $totalReviews) * 100) : 0;
        
        // Only include staff with at least 5 reviews
        if ($totalReviews < 5) {
            continue;
        }
        
        // Extract common topics
        $topTopics = self::extractStaffTopics(collect($reviewsArray));
        
        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'position' => $staff->job_title ?? 'Staff',
            'image' => $staff->image ?? null,
            'avg_rating' => round($avgRating, 1),
            'review_count' => $totalReviews,
            'sentiment_score' => $sentimentPercentage,
            'sentiment_label' => self::getSentimentLabelByPercentage($sentimentPercentage),
            'top_topics' => array_slice($topTopics, 0, 3), // Top 3 topics
            'recent_activity' => $latestReviewDate 
                ? $latestReviewDate->diffForHumans() 
                : 'No recent activity'
        ];
    }

    // Sort by rating, then by review count
    usort($staffPerformance, function ($a, $b) {
        if ($b['avg_rating'] == $a['avg_rating']) {
            return $b['review_count'] <=> $a['review_count'];
        }
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    // Take top 3
    $staffPerformance = array_slice($staffPerformance, 0, 3);

    return [
        'total_staff_reviewed' => count($staffGroups),
        'staff' => $staffPerformance
    ];
}
  public static  function calculatePerformanceOverviewFromReviewValue($reviews)
    {
        if ($reviews instanceof Builder) {
            $reviews = $reviews->get(); // convert to Collection
        }

        $totalSubmissions = $reviews->count();

        $averageScore = $totalSubmissions > 0
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        // Fix date comparisons
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return [
            'total_submissions' => $totalSubmissions,
            'average_score' => $averageScore,
            'score_out_of' => 5,
            'sentiment_distribution' => [
                'positive' => $totalSubmissions > 0 ? round(($positiveCount / $totalSubmissions) * 100) : 0,
                'neutral' => $totalSubmissions > 0 ? round(($neutralCount / $totalSubmissions) * 100) : 0,
                'negative' => $totalSubmissions > 0 ? round(($negativeCount / $totalSubmissions) * 100) : 0
            ],
            'submissions_today' => $reviews->filter(function ($review) use ($today) {
                return $review->created_at->isSameDay($today);
            })->count(),
            'submissions_this_week' => $reviews->filter(function ($review) use ($startOfWeek, $endOfWeek) {
                return $review->created_at->between($startOfWeek, $endOfWeek);
            })->count(),
            'submissions_this_month' => $reviews->filter(function ($review) use ($startOfMonth, $endOfMonth) {
                return $review->created_at->between($startOfMonth, $endOfMonth);
            })->count(),
            'guest_reviews_count' => $reviews->whereNotNull('guest_id')->count(),
            'user_reviews_count' => $reviews->whereNotNull('user_id')->count(),
            'overall_reviews_count' => $reviews->where('is_overall', 1)->count(),
            'survey_reviews_count' => $reviews->whereNotNull('survey_id')->count()
        ];
    }
  public static  function getReviewSamples($reviews, $limit = 2)
    {
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)
            ->sortByDesc('created_at')
            ->take($limit);

        $constructiveReviews = $reviews->whereBetween('sentiment_score', [0.4, 0.69])
            ->sortByDesc('created_at')
            ->take($limit);

        $negativeReviews = $reviews->where('sentiment_score', '<', 0.4)
            ->sortByDesc('created_at')
            ->take($limit);

        return [
            'positive' => $positiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'constructive' => $constructiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'neutral' => $negativeReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray()
        ];
    }
public static function getSubmissionsOverTime($reviews, $period)
{
    $endDate = Carbon::now();
    $startDate = match ($period) {
        '7d' => Carbon::now()->subDays(7),
        '90d' => Carbon::now()->subDays(90),
        '1y' => Carbon::now()->subYear(),
        default => Carbon::now()->subDays(30) // 30d
    };
    
    $groupFormat = match ($period) {
        '7d' => 'd-m-Y', // Daily for 7 days
        '90d', '1y' => 'm-Y', // Monthly for 90 days and 1 year
        default => 'd-m-Y' // Daily for 30 days
    };

    // Check if $reviews is a Builder instance and execute the query
    if ($reviews instanceof \Illuminate\Database\Eloquent\Builder) {
        $reviews = $reviews->get();
    }
    
    // Now convert to array for consistent handling
    $reviewsArray = is_array($reviews) ? $reviews : $reviews->toArray();

    // Filter reviews manually
    $filteredReviews = [];
    foreach ($reviewsArray as $review) {
        // Handle both array and object access (in case toArray() didn't convert nested objects)
        $createdAt = is_array($review) 
            ? ($review['created_at'] ?? null) 
            : ($review->created_at ?? null);
            
        if (!$createdAt) continue;
        
        $reviewDate = Carbon::parse($createdAt);
        if ($reviewDate->between($startDate, $endDate)) {
            $filteredReviews[] = $review;
        }
    }

    // Manual grouping by period
    $submissionsByPeriod = [];
    foreach ($filteredReviews as $review) {
        // Handle both array and object access
        $createdAt = is_array($review) 
            ? ($review['created_at'] ?? null) 
            : ($review->created_at ?? null);
            
        if (!$createdAt) continue;
        
        $periodKey = Carbon::parse($createdAt)->format($groupFormat);
        
        if (!isset($submissionsByPeriod[$periodKey])) {
            $submissionsByPeriod[$periodKey] = [
                'total_rating' => 0,
                'total_sentiment' => 0,
                'count' => 0
            ];
        }
        
        // Get rating and sentiment
        $rating = is_array($review) 
            ? ($review['rate'] ?? 0) 
            : ($review->rate ?? 0);
            
        $sentiment = is_array($review) 
            ? ($review['sentiment_score'] ?? 0) 
            : ($review->sentiment_score ?? 0);
        
        $submissionsByPeriod[$periodKey]['total_rating'] += $rating;
        $submissionsByPeriod[$periodKey]['total_sentiment'] += $sentiment;
        $submissionsByPeriod[$periodKey]['count']++;
    }

    // Format the data with manual calculations
    $formattedData = [];
    $peakSubmissions = 0;
    
    foreach ($submissionsByPeriod as $periodKey => $data) {
        $count = $data['count'];
        $avgRating = $count > 0 ? $data['total_rating'] / $count : 0;
        $avgSentiment = $count > 0 ? $data['total_sentiment'] / $count : 0;
        
        $formattedData[$periodKey] = [
            'submissions_count' => $count,
            'average_rating' => round($avgRating, 1),
            'sentiment_score' => round($avgSentiment * 100, 1)
        ];
        
        if ($count > $peakSubmissions) {
            $peakSubmissions = $count;
        }
    }

    // Fill in missing periods
    $filledData = fillMissingPeriods($formattedData, $startDate, $endDate, $groupFormat);

    return [
        'period' => $period,
        'data' => $filledData,
        'total_submissions' => count($filteredReviews),
        'peak_submissions' => $peakSubmissions,
        'date_range' => [
            'start' => $startDate->format('d-m-Y'),
            'end' => $endDate->format('d-m-Y')
        ]
    ];
}



  public static  function getRecentSubmissions($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $userName = getUserName($review);

                return [
                    'review_id' => $review->id,
                    'user_name' => $userName,
                    'rating' => $review->rate,
                    'comment' => $review->comment,
                    'submission_date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('d-m-Y H:i:s'),
                    'is_guest' => !is_null($review->guest_id),
                    'is_overall' => (bool)$review->is_overall,
                    'sentiment_score' => $review->sentiment_score,
                    'survey_name' => $review->survey ? $review->survey->name : null,
                    'staff_name' => $review->staff ? $review->staff->name : null,
                    "calculated_rating" => $review->calculated_rating ?? null,
                ];
            })
            ->values()
            ->toArray();
    }
  public static   function getRatingGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A is performing better";
        } elseif ($gap < 0) {
            return "Staff B is performing better";
        } else {
            return "Both staff are performing equally";
        }
    }
   public static  function getRecommendedTraining($reviews)
    {
        $trainingRecommendations = [];

        // Analyze reviews for training needs
        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Check for conflict resolution needs
        if (strpos($textLower, 'escalat') !== false || strpos($textLower, 'conflict') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Advanced Conflict Resolution',
                'description' => 'Recommended based on feedback regarding complex customer escalations.',
                'priority' => 'high',
                'category' => 'communication'
            ];
        }

        // Check for technical knowledge gaps
        if (strpos($textLower, 'technical') !== false || strpos($textLower, 'knowledge') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Technical Product Training',
                'description' => 'Recommended to improve product knowledge and technical expertise.',
                'priority' => 'medium',
                'category' => 'knowledge'
            ];
        }

        // Check for upselling opportunities
        if (strpos($textLower, 'upsell') !== false || strpos($textLower, 'recommend') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Sales and Upselling Techniques',
                'description' => 'Recommended to enhance sales skills and product recommendation abilities.',
                'priority' => 'medium',
                'category' => 'sales'
            ];
        }

        // Default training if no specific needs detected
        if (empty($trainingRecommendations)) {
            $trainingRecommendations[] = [
                'title' => 'Customer Service Excellence',
                'description' => 'General customer service skills enhancement.',
                'priority' => 'low',
                'category' => 'communication'
            ];
        }

        return $trainingRecommendations;
    }
  public static   function analyzeSkillGaps($reviews)
    {
        $strengths = [];
        $improvement_areas = [];

        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Analyze strengths
        if (strpos($textLower, 'communicat') !== false || strpos($textLower, 'explain') !== false) {
            $strengths[] = 'Communication';
        }
        if (strpos($textLower, 'solve') !== false || strpos($textLower, 'resolve') !== false) {
            $strengths[] = 'Problem Solving';
        }
        if (strpos($textLower, 'patient') !== false) {
            $strengths[] = 'Patience';
        }
        if (strpos($textLower, 'professional') !== false) {
            $strengths[] = 'Professionalism';
        }

        // Analyze improvement areas
        if (strpos($textLower, 'technical') !== false && strpos($textLower, 'know') === false) {
            $improvement_areas[] = 'Technical Knowledge';
        }
        if (strpos($textLower, 'upsell') !== false) {
            $improvement_areas[] = 'Upselling';
        }
        if (strpos($textLower, 'slow') !== false) {
            $improvement_areas[] = 'Process Efficiency';
        }

        // Remove duplicates
        $strengths = array_unique($strengths);
        $improvement_areas = array_unique($improvement_areas);

        return [
            'strengths' => array_values($strengths),
            'improvement_areas' => array_values($improvement_areas)
        ];
    }
  public static   function calculateCustomerTone($reviews)
    {
        $toneMetrics = [
            'friendliness' => ['friendly', 'nice', 'kind', 'pleasant', 'warm'],
            'patience' => ['patient', 'calm', 'understanding', 'tolerant'],
            'professionalism' => ['professional', 'expert', 'knowledgeable', 'competent']
        ];

        $results = [];

        foreach ($toneMetrics as $tone => $keywords) {
            $matchingReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($matchingReviews->count() > 0) {
                $positiveMatches = $matchingReviews->where('sentiment_score', '>=', 0.7)->count();
                $percentage = round(($positiveMatches / $matchingReviews->count()) * 100);
            } else {
                $percentage = 0;
            }

            $results[$tone] = $percentage;
        }

        return $results;
    }
   public static   function calculateSentimentDistribution($reviews)
    {
        $total = $reviews->count();

        if ($total === 0) {
            return ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $positive = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negative = $reviews->where('sentiment_score', '<', 0.4)->count();

        return [
            'positive' => round(($positive / $total) * 100),
            'neutral' => round(($neutral / $total) * 100),
            'negative' => round(($negative / $total) * 100)
        ];
    }
  public static   function calculateComplimentRatio($reviews)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                'compliments_percentage' => 0,
                'complaints_percentage' => 0,
                'compliments_count' => 0,
                'complaints_count' => 0
            ];
        }

        $compliments = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $complaints = $reviews->where('sentiment_score', '<', 0.4)->count();
        $neutral = $totalReviews - $compliments - $complaints;

        return [
            'compliments_percentage' => round(($compliments / $totalReviews) * 100),
            'complaints_percentage' => round(($complaints / $totalReviews) * 100),
            'neutral_percentage' => round(($neutral / $totalReviews) * 100),
            'compliments_count' => $compliments,
            'complaints_count' => $complaints,
            'neutral_count' => $neutral
        ];
    }
 public static function getAllStaffMetricsFromReviewValue($reviews)
{
    // Manual grouping by staff_id
    $staffGroups = [];
    foreach ($reviews as $review) {
        if ($review->staff_id) {
            $staffGroups[$review->staff_id][] = $review;
        }
    }

    $staffMetrics = [];
    
    foreach ($staffGroups as $staffId => $reviewsArray) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        $totalRating = 0;
        $totalSentiment = 0;
        $totalReviews = count($reviewsArray);
        $compliments = 0;
        $complaints = 0;
        $neutral = 0;
        
        foreach ($reviewsArray as $review) {
            // Sum up calculated rating
            $totalRating += $review->calculated_rating ?? 0;
            
            // Sum up sentiment score
            $sentimentScore = $review->sentiment_score ?? 0;
            $totalSentiment += $sentimentScore;
            
            // Count sentiment categories
            if ($sentimentScore >= 0.7) {
                $compliments++;
            } elseif ($sentimentScore < 0.4) {
                $complaints++;
            } else {
                $neutral++;
            }
        }
        
        // Calculate averages
        $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
        $avgSentiment = $totalReviews > 0 ? $totalSentiment / $totalReviews : 0;
        
        $staffMetrics[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'position' => $staff->job_title ?? 'Staff',
            'avg_rating' => round($avgRating, 1),
            'sentiment_score' => self::getSentimentLabel($avgSentiment),
            'compliments_count' => $compliments,
            'complaints_count' => $complaints,
            'neutral_count' => $neutral,
            'total_reviews' => $totalReviews,
            'sentiment_numeric' => round($avgSentiment * 100)
        ];
    }

    // Sort by average rating descending
    usort($staffMetrics, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return $staffMetrics;
}
 public static function generateAiSummary($reviews)
    {
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();
        $total = $reviews->count();

        if ($total == 0) return 'No reviews to analyze.';

        $positivePercent = round(($positiveCount / $total) * 100);
        $negativePercent = round(($negativeCount / $total) * 100);

        return "Customers are {$positivePercent}% positive and {$negativePercent}% negative. " .
            "Common themes include staff friendliness, service speed, and occasional cleanliness concerns.";
    }

   public static  function extractIssuesFromSuggestions($suggestions)
    {
        $issues = collect($suggestions)
            ->filter(fn($s) => stripos($s, 'consider') !== false || stripos($s, 'implement') !== false)
            ->map(fn($s) => [
                'issue' => $s,
                'mention_count' => 1
            ])
            ->take(3)
            ->values();

        return $issues->isEmpty() ? [[
            'issue' => 'No major issues detected.',
            'mention_count' => 0
        ]] : $issues->toArray();
    }


   public static  function generateStaffSuggestions($weaknesses)
    {
        $suggestions = [];

        foreach ($weaknesses as $weakness) {
            switch ($weakness) {
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
            }
        }

        return $suggestions;
    } 
    public static function getReviewFeed($businessId, $dateRange, $limit = 10)
    {
        $reviews = ReviewNew::with(['user', 'guest_user', 'staff', 'value.tag', 'value'])
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->orderBy('created_at', 'desc')
            ->globalFilters(0, $businessId)
            ->limit($limit)
            ->withCalculatedRating()
            ->get();

        return $reviews->map(function ($review) {
            // Use the calculated_rating from the query, no need to recalculate
            $calculatedRating = (float) $review->calculated_rating; // Cast to float

            return [
                'id' => $review->id,
                'responded_at' => $review->responded_at,
                'rating' => ($calculatedRating ?? 0) . '/5',
                'calculated_rating' => $calculatedRating,
                'author' => $review->user?->name ?? $review->guest_user?->full_name ?? 'Anonymous',
                'time_ago' => $review->created_at->diffForHumans(),
                'comment' => $review->comment,
                'staff_name' => $review->staff?->name,
                'tags' => $review->value->map(fn($v) => $v->tag->tag ?? null)->filter()->unique()->values()->toArray(),
                'is_voice' => $review->is_voice_review,
                'sentiment' =>  self::getSentimentLabel($review->sentiment_score),
                'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
            ];
        });
    }
    /**
     * Step 1: AI Moderation Pipeline (Improved)
     */

 public static function getAudioDuration($filePath)
    {
        try {
            $getID3 = new getID3();
            $fileInfo = $getID3->analyze($filePath);
            return $fileInfo['playtime_seconds'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }



   public static function getSentimentLabelByPercentage($percentage)
    {
        if ($percentage >= 70) {
            return 'Excellent';
        } elseif ($percentage >= 50) {
            return 'Good';
        } elseif ($percentage >= 30) {
            return 'Average';
        } else {
            return 'Needs Improvement';
        }
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




{
  "model": "gpt-4o-mini",
  "temperature": 0.2,
  "max_tokens": 900,
  "messages": [
    {
      "role": "system",
      "content": "You are an AI Experience Intelligence Engine. Analyze reviews fairly and return ONLY valid JSON exactly matching this schema: { \"language\": {\"detected\": \"\", \"translated_text\": \"\"}, \"sentiment\": {\"label\": \"\", \"score\": 0.0}, \"emotion\": {\"primary\": \"\", \"intensity\": \"\"}, \"moderation\": {\"is_abusive\": false, \"safe_for_public_display\": true}, \"themes\": [], \"category_analysis\": [], \"staff_intelligence\": {\"staff_id\": \"\", \"staff_name\": \"\", \"mentioned_explicitly\": false, \"sentiment_towards_staff\": \"\", \"soft_skill_scores\": {}, \"training_recommendations\": [], \"risk_level\": \"\"}, \"service_unit_intelligence\": {\"unit_type\": \"\", \"unit_id\": \"\", \"issues_detected\": [], \"maintenance_required\": false}, \"business_insights\": {\"root_cause\": \"\", \"repeat_issue_likelihood\": \"\"}, \"recommendations\": {\"business_actions\": [], \"staff_actions\": []}, \"alerts\": {\"triggered\": false}, \"explainability\": {\"decision_basis\": [], \"confidence_score\": 0.0}, \"summary\": {\"one_line\": \"\", \"manager_summary\": \"\"} } Do NOT add extra fields. Do not shorten or summarize."
    },
    {
      "role": "user",
      "content": "{ \"business_ai_settings\": { \"staff_intelligence\": true, \"ignore_abusive_reviews_for_staff\": true, \"min_reviews_for_staff_score\": 3, \"confidence_threshold\": 0.7 }, \"review_metadata\": { \"source\": \"platform\", \"business_type\": \"hotel\", \"branch_id\": \"BR-101\", \"submitted_at\": \"2025-11-01T10:15:00Z\" }, \"review_content\": { \"text\": \"Ateeq served us very badly today. He was rude and ignored our requests. The room 305 was clean but service was terrible.\", \"voice_review\": false }, \"ratings\": { \"overall\": 2, \"questions\": [ {\"question_id\": \"Q1\", \"question_text\": \"Staff behavior\", \"main_category\": \"Staff\", \"sub_category\": \"Politeness\", \"rating\": 2}, {\"question_id\": \"Q2\", \"question_text\": \"Room cleanliness\", \"main_category\": \"Service\", \"sub_category\": \"Cleanliness\", \"rating\": 4} ] }, \"staff_context\": { \"staff_selected\": true, \"staff_id\": \"ST-2001\", \"staff_name\": \"Ateeq\" }, \"service_unit\": { \"unit_type\": \"Room\", \"unit_id\": \"305\" } }"
    }
  ]
}
