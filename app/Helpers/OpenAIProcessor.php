<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class OpenAIProcessor
{
    // Configuration
    private static $openaiApiKey;
    private static $model = 'gpt-4o-mini';
    private static $temperature = 0.2;
    private static $maxTokens = 900;
    
    /**
     * Initialize OpenAI API Key
     */
    private static function initOpenAI()
    {
        if (!self::$openaiApiKey) {
            self::$openaiApiKey = config('services.openai.api_key');
            
            if (empty(self::$openaiApiKey)) {
                throw new \Exception('OpenAI API key not configured');
            }
        }
    }
    
    /**
     * Process review with OpenAI - Single API call for all modules
     */
    public static function processReviewWithOpenAI($payload)
    {
        self::initOpenAI();
        
        try {
            // Check cache first
            $cacheKey = 'ai_review_' . md5(json_encode($payload));
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // Prepare OpenAI request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . self::$openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::$model,
                'temperature' => self::$temperature,
                'max_tokens' => self::$maxTokens,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => self::getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($payload)
                    ]
                ],
                'response_format' => ['type' => 'json_object']
            ]);
            
            if ($response->successful()) {
                $result = $response->json();
                
                if (isset($result['choices'][0]['message']['content'])) {
                    $content = $result['choices'][0]['message']['content'];
                    $parsedResult = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Cache::put($cacheKey, $parsedResult, 3600); // Cache for 1 hour
                        return $parsedResult;
                    }
                }
            }
            
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            
            return self::getFallbackResponse($payload);
            
        } catch (\Exception $e) {
            Log::error('OpenAI processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return self::getFallbackResponse($payload);
        }
    }
    
    /**
     * Get system prompt for OpenAI
     */
    private static function getSystemPrompt()
    {
        return <<<PROMPT
You are an AI Experience Intelligence Engine. Analyze reviews fairly and return ONLY valid JSON exactly matching this schema:

{
  "language": {
    "detected": "",
    "translated_text": ""
  },
  "sentiment": {
    "label": "",
    "score": 0.0
  },
  "emotion": {
    "primary": "",
    "intensity": ""
  },
  "moderation": {
    "is_abusive": false,
    "safe_for_public_display": true
  },
  "themes": [],
  "category_analysis": [],
  "staff_intelligence": {
    "staff_id": "",
    "staff_name": "",
    "mentioned_explicitly": false,
    "sentiment_towards_staff": "",
    "soft_skill_scores": {},
    "training_recommendations": [],
    "risk_level": ""
  },
  "service_unit_intelligence": {
    "unit_type": "",
    "unit_id": "",
    "issues_detected": [],
    "maintenance_required": false
  },
  "business_insights": {
    "root_cause": "",
    "repeat_issue_likelihood": ""
  },
  "recommendations": {
    "business_actions": [],
    "staff_actions": []
  },
  "alerts": {
    "triggered": false
  },
  "explainability": {
    "decision_basis": [],
    "confidence_score": 0.0
  },
  "summary": {
    "one_line": "",
    "manager_summary": ""
  }
}

IMPORTANT RULES:
1. Follow ALL business_ai_settings rules provided in the user content
2. For staff_intelligence: Only analyze staff if staff_selected is true and staff_id is provided
3. For moderation: Use context-aware analysis, don't flag valid criticism as abuse
4. For language: Detect language and provide English translation if not English
5. For category_analysis: Map to provided ratings categories when available
6. Return valid JSON only, no additional text

PROMPT;
    }
    
    /**
     * Create fallback response when OpenAI fails
     */
    private static function getFallbackResponse($payload)
    {
        $reviewText = $payload['review_content']['text'] ?? '';
        $staffId = $payload['staff_context']['staff_id'] ?? null;
        
        // Use existing fallback methods
        $moderation = self::aiModeration($reviewText);
        $sentimentScore = self::analyzeSentimentImprovedFallback($reviewText);
        $topics = self::extractTopics($reviewText);
        $emotion = self::detectEmotionImprovedFallback($reviewText);
        $staffSuggestions = self::analyzeStaffPerformance($reviewText, $staffId, $sentimentScore);
        $recommendations = self::generateRecommendations($topics, $sentimentScore);
        
        // Build fallback response matching the schema
        return [
            'language' => [
                'detected' => 'en', // Fallback assumption
                'translated_text' => $reviewText
            ],
            'sentiment' => [
                'label' => self::getSentimentLabel($sentimentScore),
                'score' => $sentimentScore
            ],
            'emotion' => [
                'primary' => $emotion,
                'intensity' => 'medium'
            ],
            'moderation' => [
                'is_abusive' => $moderation['should_block'] ?? false,
                'safe_for_public_display' => !($moderation['should_block'] ?? false)
            ],
            'themes' => array_map(function($topic) {
                return [
                    'topic' => $topic,
                    'type' => 'general',
                    'confidence' => 0.7
                ];
            }, $topics),
            'category_analysis' => self::generateCategoryAnalysis($payload, $sentimentScore),
            'staff_intelligence' => self::generateStaffIntelligence($payload, $reviewText, $staffSuggestions, $sentimentScore),
            'service_unit_intelligence' => self::generateServiceUnitIntelligence($payload),
            'business_insights' => [
                'root_cause' => self::extractRootCause($topics),
                'repeat_issue_likelihood' => 'medium'
            ],
            'recommendations' => [
                'business_actions' => $recommendations,
                'staff_actions' => $staffSuggestions
            ],
            'alerts' => [
                'triggered' => ($moderation['severity_score'] ?? 0) >= 5 || $sentimentScore < 0.3
            ],
            'explainability' => [
                'decision_basis' => ['Fallback analysis due to API failure'],
                'confidence_score' => 0.5
            ],
            'summary' => [
                'one_line' => self::generateOneLineSummary($reviewText, $sentimentScore),
                'manager_summary' => self::generateManagerSummary($reviewText, $sentimentScore, $topics)
            ]
        ];
    }
    
    /**
     * Generate category analysis from ratings
     */
    private static function generateCategoryAnalysis($payload, $sentimentScore)
    {
        $categories = [];
        
        if (isset($payload['ratings']['questions'])) {
            foreach ($payload['ratings']['questions'] as $question) {
                $rating = $question['rating'] ?? 0;
                $sentiment = $rating >= 3 ? 'positive' : ($rating >= 2 ? 'neutral' : 'negative');
                $severity = $rating >= 4 ? 'low' : ($rating >= 2 ? 'medium' : 'high');
                
                $categories[] = [
                    'main_category' => $question['main_category'] ?? 'General',
                    'sub_category' => $question['sub_category'] ?? 'General',
                    'sentiment' => $sentiment,
                    'severity' => $severity
                ];
            }
        }
        
        return $categories;
    }
    
    /**
     * Generate staff intelligence
     */
    private static function generateStaffIntelligence($payload, $reviewText, $staffSuggestions, $sentimentScore)
    {
        $staffContext = $payload['staff_context'] ?? [];
        
        if (!($staffContext['staff_selected'] ?? false)) {
            return [
                'staff_id' => '',
                'staff_name' => '',
                'mentioned_explicitly' => false,
                'sentiment_towards_staff' => '',
                'soft_skill_scores' => new \stdClass(),
                'training_recommendations' => [],
                'risk_level' => ''
            ];
        }
        
        $staffName = $staffContext['staff_name'] ?? '';
        $mentioned = strpos(strtolower($reviewText), strtolower($staffName)) !== false;
        $sentimentTowardsStaff = $sentimentScore >= 0.7 ? 'positive' : ($sentimentScore >= 0.4 ? 'neutral' : 'negative');
        
        // Calculate soft skill scores based on sentiment and topics
        $softSkills = [
            'politeness' => max(1, min(5, round($sentimentScore * 5))),
            'communication' => max(1, min(5, round($sentimentScore * 4 + 1))),
            'empathy' => max(1, min(5, round($sentimentScore * 3 + 2))),
            'professionalism' => max(1, min(5, round($sentimentScore * 4 + 1)))
        ];
        
        $riskLevel = $sentimentScore < 0.3 ? 'high' : ($sentimentScore < 0.5 ? 'medium' : 'low');
        
        return [
            'staff_id' => $staffContext['staff_id'] ?? '',
            'staff_name' => $staffName,
            'mentioned_explicitly' => $mentioned,
            'sentiment_towards_staff' => $sentimentTowardsStaff,
            'soft_skill_scores' => $softSkills,
            'training_recommendations' => $staffSuggestions,
            'risk_level' => $riskLevel
        ];
    }
    
    /**
     * Generate service unit intelligence
     */
    private static function generateServiceUnitIntelligence($payload)
    {
        $serviceUnit = $payload['service_unit'] ?? [];
        
        if (empty($serviceUnit['unit_id'])) {
            return [
                'unit_type' => '',
                'unit_id' => '',
                'issues_detected' => [],
                'maintenance_required' => false
            ];
        }
        
        // Simple detection based on text
        $reviewText = $payload['review_content']['text'] ?? '';
        $issues = [];
        
        if (preg_match('/\b(dirty|broken|damaged|leak|stain|smell|noisy)\b/i', $reviewText)) {
            $issues[] = 'physical_issue';
        }
        
        return [
            'unit_type' => $serviceUnit['unit_type'] ?? 'Room',
            'unit_id' => $serviceUnit['unit_id'] ?? '',
            'issues_detected' => $issues,
            'maintenance_required' => !empty($issues)
        ];
    }
    
    /**
     * Extract root cause from topics
     */
    private static function extractRootCause($topics)
    {
        if (empty($topics)) {
            return 'unknown';
        }
        
        $priorityTopics = [
            'staff politeness' => 'staff_behavior',
            'service quality' => 'service_process',
            'food quality' => 'product_quality',
            'cleanliness' => 'hygiene_standards',
            'wait time' => 'operational_efficiency'
        ];
        
        foreach ($priorityTopics as $topic => $cause) {
            if (in_array($topic, $topics)) {
                return $cause;
            }
        }
        
        return reset($topics);
    }
    
    /**
     * Generate one-line summary
     */
    private static function generateOneLineSummary($reviewText, $sentimentScore)
    {
        $shortText = substr($reviewText, 0, 100) . (strlen($reviewText) > 100 ? '...' : '');
        
        if ($sentimentScore >= 0.7) {
            return "Positive review: " . $shortText;
        } elseif ($sentimentScore >= 0.4) {
            return "Neutral review: " . $shortText;
        } else {
            return "Negative review: " . $shortText;
        }
    }
    
    /**
     * Generate manager summary
     */
    private static function generateManagerSummary($reviewText, $sentimentScore, $topics)
    {
        $sentiment = self::getSentimentLabel($sentimentScore);
        $topicStr = !empty($topics) ? 'Focus areas: ' . implode(', ', $topics) : 'No specific topics identified.';
        
        return "{$sentiment} review with sentiment score {$sentimentScore}. {$topicStr}";
    }
    
    /**
     * Main processing method - handles both OpenAI and fallback
     */
    public static function processReview($text, $staff_id = null, $fullPayload = null)
    {
        try {
            // If full payload is provided, use OpenAI
            if ($fullPayload) {
                $openAIResult = self::processReviewWithOpenAI($fullPayload);
                
                // Convert to legacy format for backward compatibility
                return self::convertToLegacyFormat($openAIResult, $text, $staff_id);
            }
            
            // Otherwise use existing fallback
            return self::processReviewFallback($text, $staff_id);
            
        } catch (\Exception $e) {
            Log::error('Review processing failed', [
                'error' => $e->getMessage()
            ]);
            
            return self::processReviewFallback($text, $staff_id);
        }
    }
    
    /**
     * Convert OpenAI result to legacy format
     */
    private static function convertToLegacyFormat($openAIResult, $text, $staff_id)
    {
        $sentimentScore = $openAIResult['sentiment']['score'] ?? 0.5;
        $sentimentLabel = $openAIResult['sentiment']['label'] ?? 'neutral';
        
        // Extract themes as topics
        $topics = [];
        foreach ($openAIResult['themes'] ?? [] as $theme) {
            $topics[] = $theme['topic'] ?? '';
        }
        
        // Extract recommendations
        $recommendations = array_merge(
            $openAIResult['recommendations']['business_actions'] ?? [],
            $openAIResult['recommendations']['staff_actions'] ?? []
        );
        
        // Staff suggestions from training recommendations
        $staffSuggestions = $openAIResult['staff_intelligence']['training_recommendations'] ?? [];
        
        // Moderation results
        $moderation = [
            'issues_found' => $openAIResult['moderation']['is_abusive'] ? ['abusive_content'] : [],
            'severity_score' => $openAIResult['moderation']['is_abusive'] ? 5 : 0,
            'action_taken' => $openAIResult['moderation']['is_abusive'] ? 'block' : 'allow',
            'should_block' => $openAIResult['moderation']['is_abusive'] ?? false,
            'action_message' => $openAIResult['moderation']['is_abusive'] ? 
                'Content blocked due to inappropriate content' : 'Content approved'
        ];
        
        // Key phrases from summary
        $keyPhrases = [];
        if (isset($openAIResult['summary']['one_line'])) {
            $keyPhrases = array_slice(
                explode(' ', preg_replace('/[^\w\s]/', '', $openAIResult['summary']['one_line'])),
                0, 5
            );
        }
        
        return [
            'moderation' => $moderation,
            'sentiment' => $sentimentScore,
            'sentiment_score' => $sentimentScore,
            'sentiment_label' => $sentimentLabel,
            'sentiment_category' => self::getSentimentCategory($sentimentScore),
            'sentiment_percentage' => self::getSentimentPercentage($sentimentScore),
            'topics' => $topics,
            'staff_suggestions' => $staffSuggestions,
            'recommendations' => $recommendations,
            'emotion' => $openAIResult['emotion']['primary'] ?? 'neutral',
            'key_phrases' => $keyPhrases,
            'ai_sentiment_score' => self::getSentimentPercentage($sentimentScore),
            'is_positive' => $sentimentScore >= 0.7,
            'is_negative' => $sentimentScore < 0.4,
            'is_neutral' => $sentimentScore >= 0.4 && $sentimentScore < 0.7,
            'openai_result' => $openAIResult // Store full result for explainability
        ];
    }
    
    /**
     * Process review using fallback methods
     */
    private static function processReviewFallback($text, $staff_id = null)
    {
        // Keep your existing fallback processing logic
        $moderation = self::aiModeration($text);
        $sentiment_score = self::analyzeSentimentImprovedFallback($text);
        $topics = self::extractTopics($text);
        $sentiment_label = self::getSentimentLabel($sentiment_score);
        $staff_suggestions = self::analyzeStaffPerformance($text, $staff_id, $sentiment_score);
        $recommendations = self::generateRecommendations($topics, $sentiment_score);
        $emotion = self::detectEmotionImprovedFallback($text);
        $key_phrases = self::extractKeyPhrases($text);

        return [
            'moderation' => $moderation,
            'sentiment' => $sentiment_score,
            'sentiment_score' => $sentiment_score,
            'sentiment_label' => $sentiment_label,
            'sentiment_category' => self::getSentimentCategory($sentiment_score),
            'sentiment_percentage' => self::getSentimentPercentage($sentiment_score),
            'topics' => $topics,
            'staff_suggestions' => $staff_suggestions,
            'recommendations' => $recommendations,
            'emotion' => $emotion,
            'key_phrases' => $key_phrases,
            'ai_sentiment_score' => self::getSentimentPercentage($sentiment_score),
            'is_positive' => $sentiment_score >= 0.7,
            'is_negative' => $sentiment_score < 0.4,
            'is_neutral' => $sentiment_score >= 0.4 && $sentiment_score < 0.7
        ];
    }
    
    /**
     * The following methods are your existing fallback methods
     * I'll include them here for completeness
     */
    
    public static function aiModeration($text)
    {
        // Your existing aiModeration method
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
                $severity += 3;
            }
        }
        
        // Check for hate speech
        foreach ($hateSpeechIndicators as $indicator) {
            if (strpos($textLower, $indicator) !== false && !self::isNegated($textLower, $indicator)) {
                $issues[] = 'hate_speech';
                $severity += 4;
            }
        }
        
        // Check for spam
        foreach ($spamPatterns as $pattern) {
            if (strpos($textLower, $pattern) !== false) {
                $issues[] = 'spam_content';
                $severity += 2;
            }
        }
        
        // Check for moderate criticism
        foreach ($moderateCriticism as $word => $wordSeverity) {
            if (strpos($textLower, $word) !== false && !self::isNegated($textLower, $word)) {
                $isAcceptable = false;
                foreach ($acceptableCriticism as $acceptable) {
                    if ($word === $acceptable) {
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
        
        // Check for excessive negativity
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
        
        // Determine action
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
    
    private static function analyzeSentimentImprovedFallback($text)
    {
        // Your existing improved fallback sentiment analysis
        $textLower = strtolower($text);
        
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
        
        $posScore = 0;
        $negScore = 0;
        
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
        
        foreach ($sentences as $sentence) {
            $isNegated = in_array(trim($sentence), $negationSentences);
            
            foreach ($positiveWords as $word => $weight) {
                if (strpos($sentence, $word) !== false) {
                    if ($isNegated) {
                        $negScore += $weight;
                    } else {
                        $posScore += $weight;
                    }
                }
            }
            
            foreach ($negativeWords as $word => $weight) {
                if (strpos($sentence, $word) !== false) {
                    if ($isNegated) {
                        $posScore += $weight;
                    } else {
                        $negScore += $weight;
                    }
                }
            }
        }
        
        if (preg_match('/not happy/i', $text)) {
            $posScore = max(0, $posScore - 2);
            $negScore += 2;
        }
        
        if (preg_match('/not bad/i', $text)) {
            $posScore += 0.5;
            $negScore = max(0, $negScore - 0.5);
        }
        
        $totalScore = $posScore + $negScore;
        
        if ($totalScore === 0) {
            $neutralIndicators = ['okay', 'fine', 'average', 'normal', 'regular', 'decent', 'acceptable'];
            foreach ($neutralIndicators as $indicator) {
                if (strpos($textLower, $indicator) !== false) {
                    return 0.5;
                }
            }
            
            if (strpos($textLower, '!') !== false && strlen($text) > 50) {
                return 0.6;
            }
            return 0.5;
        }
        
        $sentimentRatio = $posScore / $totalScore;
        
        if ($sentimentRatio >= 0.8) return 0.9;
        if ($sentimentRatio >= 0.7) return 0.8;
        if ($sentimentRatio >= 0.6) return 0.7;
        if ($sentimentRatio >= 0.5) return 0.6;
        if ($sentimentRatio >= 0.4) return 0.5;
        if ($sentimentRatio >= 0.3) return 0.4;
        if ($sentimentRatio >= 0.2) return 0.3;
        if ($sentimentRatio >= 0.1) return 0.2;
        return 0.1;
    }
    
    public static function extractTopics($text)
    {
        // Your existing extractTopics method
        $textLower = strtolower($text);
        $topics = [];
        
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
        
        foreach ($topicDefinitions as $topic => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    if (!self::isNegated($textLower, $keyword)) {
                        $topics[] = $topic;
                        break;
                    }
                }
            }
        }
        
        if (empty($topics)) {
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
            
            if ($hasPositive || $hasNegative) {
                if (preg_match('/\b(food|dish|meal|taste|flavor|cuisine|menu|restaurant|eat|dining)\b/i', $text)) {
                    $topics[] = 'food quality';
                }
                
                if (preg_match('/\b(service|staff|waiter|waitress|server|host|manager|employee)\b/i', $text)) {
                    $topics[] = 'service quality';
                }
                
                if (preg_match('/\b(place|location|restaurant|establishment|venue|spot|joint)\b/i', $text)) {
                    $topics[] = 'location';
                }
                
                if (preg_match('/\b(price|cost|expensive|cheap|affordable|overpriced|value|worth)\b/i', $text)) {
                    $topics[] = 'price value';
                }
            }
        }
        
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
        
        return array_values(array_unique($topics));
    }
    
    public static function analyzeStaffPerformance($text, $staff_id, $sentiment_score = null)
    {
        // Your existing staff performance analysis
        if (!$staff_id) {
            return [];
        }
        
        if ($sentiment_score === null) {
            $sentiment_score = self::analyzeSentimentImprovedFallback($text);
        }
        
        $textLower = strtolower($text);
        $suggestions = [];
        
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
        
        foreach ($negativePatterns as $pattern => $suggestion) {
            if (preg_match($pattern, $text) && !self::isNegatedPattern($text, $pattern)) {
                $suggestions[] = $suggestion;
            }
        }
        
        if (empty($suggestions) && $sentiment_score < 0.4) {
            if (preg_match('/\b(staff|waiter|waitress|server|employee|worker)\b/i', $text)) {
                $suggestions[] = 'General customer service training recommended';
            }
        }
        
        return array_unique($suggestions);
    }
    
    public static function generateRecommendations($topics, $sentiment_score)
    {
        // Your existing generateRecommendations method
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
                if ($sentiment_score <= 0.6) {
                    $recommendations[] = $recommendationMap[$topic];
                }
            }
        }
        
        return array_unique($recommendations);
    }
    
    private static function detectEmotionImprovedFallback($text)
    {
        // Your existing emotion detection fallback
        $textLower = strtolower($text);
        $sentiment = self::analyzeSentimentImprovedFallback($text);
        
        if (($sentiment < 0.4 && preg_match('/great|excellent|amazing|wonderful/i', $text)) ||
            ($sentiment > 0.7 && preg_match('/terrible|awful|horrible|worst/i', $text))) {
            return 'sarcasm';
        }
        
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
        
        foreach ($emotionPatterns as $emotion => &$data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    if (self::isNegated($textLower, $keyword)) {
                        if (in_array($emotion, ['joy', 'surprise'])) {
                            $data['weight'] -= 1;
                        } elseif (in_array($emotion, ['anger', 'sadness', 'fear'])) {
                            $data['weight'] += 0.5;
                        }
                    } else {
                        $data['weight'] += 1;
                        
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
        
        $maxWeight = 0;
        $detectedEmotion = 'neutral';
        
        foreach ($emotionPatterns as $emotion => $data) {
            if ($data['weight'] > $maxWeight) {
                $maxWeight = $data['weight'];
                $detectedEmotion = $emotion;
            }
        }
        
        if ($maxWeight === 0) {
            if ($sentiment >= 0.7) return 'joy';
            if ($sentiment <= 0.3) return 'sadness';
            return 'neutral';
        }
        
        if (($detectedEmotion === 'joy' && $sentiment < 0.4) || 
            (in_array($detectedEmotion, ['anger', 'sadness']) && $sentiment > 0.7)) {
            if ($sentiment < 0.4) return 'sadness';
            if ($sentiment > 0.7) return 'joy';
        }
        
        return $detectedEmotion;
    }
    
    public static function extractKeyPhrases($text)
    {
        // Your existing key phrase extraction
        $text = preg_replace('/[^\w\s\']/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'this', 'that', 'these', 'those', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'very', 'really', 'just', 'about', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', 'should', 'now'];
        
        $words = preg_split('/\s+/', strtolower(trim($text)));
        $phrases = [];
        
        for ($i = 0; $i < count($words) - 1; $i++) {
            $word1 = $words[$i];
            $word2 = $words[$i + 1];
            
            if (in_array($word1, $stopWords) || in_array($word2, $stopWords)) {
                continue;
            }
            
            if (strlen($word1) < 3 || strlen($word2) < 3) {
                continue;
            }
            
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
        
        for ($i = 0; $i < count($words) - 2; $i++) {
            $word1 = $words[$i];
            $word2 = $words[$i + 1];
            $word3 = $words[$i + 2];
            
            if (in_array($word1, $stopWords) || in_array($word2, $stopWords) || in_array($word3, $stopWords)) {
                continue;
            }
            
            if (strlen($word1) < 3 || strlen($word2) < 3 || strlen($word3) < 3) {
                continue;
            }
            
            $phrase = $word1 . ' ' . $word2 . ' ' . $word3;
            
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
        
        $phraseCounts = array_count_values($phrases);
        arsort($phraseCounts);
        
        $topPhrases = array_keys(array_slice($phraseCounts, 0, 5));
        
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
        // Your existing important words extraction
        $textLower = strtolower($text);
        $importantWords = [];
        
        $wordCategories = [
            'food' => ['food', 'dish', 'meal', 'dinner', 'lunch', 'breakfast', 'cuisine', 'menu'],
            'taste' => ['taste', 'flavor', 'delicious', 'tasty', 'savory', 'bland', 'spicy', 'sweet'],
            'quality' => ['quality', 'fresh', 'cooked', 'raw', 'hot', 'cold', 'warm', 'moist', 'dry'],
            'service' => ['service', 'staff', 'waiter', 'waitress', 'server', 'host', 'manager'],
            'behavior' => ['friendly', 'rude', 'polite', 'helpful', 'unhelpful', 'attentive', 'ignored'],
            'efficiency' => ['efficient', 'slow', 'fast', 'quick', 'prompt', 'delayed', 'timely'],
            'ambiance' => ['ambiance', 'atmosphere', 'environment', 'vibe', 'decor', 'lighting', 'music'],
            'comfort' => ['comfortable', 'uncomfortable', 'cozy', 'noisy', 'quiet', 'crowded', 'spacious'],
            'experience' => ['experience', 'visit', 'dining', 'meal', 'evening', 'night', 'celebration'],
            'recommendation' => ['recommend', 'return', 'again', 'never', 'always', 'favorite', 'best'],
            'price' => ['price', 'cost', 'expensive', 'cheap', 'affordable', 'worth', 'value', 'overpriced'],
            'cleanliness' => ['clean', 'dirty', 'hygiene', 'sanitary', 'spotless', 'messy', 'tidy'],
            'time' => ['wait', 'time', 'minutes', 'hours', 'reservation', 'booking', 'schedule'],
        ];
        
        foreach ($wordCategories as $category => $words) {
            foreach ($words as $word) {
                if (strpos($textLower, $word) !== false) {
                    if (!self::isNegated($textLower, $word)) {
                        $importantWords[] = $word;
                    }
                }
            }
        }
        
        return array_unique($importantWords);
    }
    
    private static function isNegated($text, $word)
    {
        // Your existing isNegated method
        $sentences = preg_split('/[.!?]+/', strtolower($text));
        
        foreach ($sentences as $sentence) {
            $pos = strpos($sentence, $word);
            if ($pos === false) continue;
            
            $negationWords = ['not', 'no', 'never', 'nothing', 'isn\'t', 'wasn\'t', 'aren\'t', 'weren\'t', 'doesn\'t', 'don\'t', 'didn\'t'];
            
            foreach ($negationWords as $negWord) {
                if (strpos($sentence, $negWord) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private static function isNegatedPattern($text, $pattern)
    {
        // Your existing isNegatedPattern method
        $textLower = strtolower($text);
        $negationWords = ['not', 'no', 'never', 'isn\'t', 'wasn\'t', 'aren\'t', 'weren\'t', 'doesn\'t', 'don\'t', 'didn\'t'];
        
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
    
    public static function getSentimentLabel(?float $score): string
    {
        if ($score === null) {
            return 'neutral';
        }
        return $score >= 0.7 ? 'positive' : ($score >= 0.4 ? 'neutral' : 'negative');
    }
    
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
    
    public static function getSentimentPercentage($score)
    {
        if ($score === null) return 50;
        return round($score * 100);
    }
}