<?php

namespace Tests\Unit;

use App\Services\Ats\SkillMatcher;
use PHPUnit\Framework\TestCase;

class SkillMatcherTest extends TestCase
{
    private function matcher(): SkillMatcher
    {
        return new SkillMatcher(require __DIR__ . '/../../config/ats_skills.php');
    }

    private function has(string $skill, string $cv): bool
    {
        return $this->matcher()->match($skill, SkillMatcher::normalize($cv)) !== null;
    }

    public function test_java_is_not_matched_by_javascript(): void
    {
        $this->assertFalse($this->has('Java', 'Skills: JavaScript, React'));
        $this->assertTrue($this->has('Java', 'Skills: Java, Spring'));
    }

    public function test_sql_is_not_matched_by_mysql(): void
    {
        $this->assertFalse($this->has('SQL', 'Databases: MySQL'));
        $this->assertTrue($this->has('MySQL', 'Databases: MySQL'));
    }

    public function test_react_native_needs_full_phrase(): void
    {
        $this->assertFalse($this->has('React Native', 'Frontend: React, Redux'));
        $this->assertTrue($this->has('React Native', 'Built apps with React Native'));
    }

    public function test_aliases(): void
    {
        $this->assertTrue($this->has('PostgreSQL', 'worked with Postgres'));
        $this->assertTrue($this->has('Kubernetes', 'deployed on k8s'));
        $this->assertTrue($this->has('JavaScript (ES6+)', 'modern JS'));
    }

    public function test_symbols(): void
    {
        $this->assertTrue($this->has('C++', 'Languages: C++, Python'));
        $this->assertFalse($this->has('C', 'Languages: C++, C#'));
        $this->assertTrue($this->has('Node.js', 'backend with node.js and express'));
        $this->assertTrue($this->has('CI/CD', 'built CI/CD pipelines'));
    }

    public function test_implied_and_composite(): void
    {
        $this->assertTrue($this->has('AWS', 'hosted files on S3'));
        $this->assertTrue($this->has('AWS (EC2, S3)', 'used EC2 and S3'));
        $this->assertFalse($this->has('AWS (EC2, Lambda)', 'used Azure and GCP'));
    }

    public function test_partition(): void
    {
        $r = $this->matcher()->partition(['PHP', 'Java', 'PHP'], 'PHP, JavaScript');
        $this->assertCount(1, $r['matched']);
        $this->assertSame(['Java'], $r['missing']);
    }
}
