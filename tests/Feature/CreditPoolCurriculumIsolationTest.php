<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\{
    User,
    Curriculum,
    CurriculumCreditPool,
    SubCategoryPool,
    PoolCourseAttachment,
    CourseType,
    Course
};

/**
 * Verify that credit pools are properly scoped to individual curricula
 * and cannot leak across different curricula.
 */
class CreditPoolCurriculumIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $chairperson;
    protected Curriculum $curriculumA;
    protected Curriculum $curriculumB;
    protected CourseType $coreType;
    protected CourseType $majorType;
    protected CourseType $subType;
    protected Course $course1;
    protected Course $course2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a chairperson user
        $this->chairperson = User::factory()->create([
            'role' => 'CHAIRPERSON',
        ]);

        // Create two different curricula
        $this->curriculumA = Curriculum::create([
            'id' => 'BSCS-2024-A',
            'name' => 'BS Computer Science 2024 A',
            'description' => 'Test Curriculum A',
            'effective_year' => 2024,
            'status' => 'active',
        ]);

        $this->curriculumB = Curriculum::create([
            'id' => 'BSCS-2024-B',
            'name' => 'BS Computer Science 2024 B',
            'description' => 'Test Curriculum B',
            'effective_year' => 2024,
            'status' => 'active',
        ]);

        // Create course types
        $this->coreType = CourseType::create([
            'id' => 'core',
            'name' => 'Core Courses',
            'color' => '#4CAF50',
        ]);

        $this->majorType = CourseType::create([
            'id' => 'major',
            'name' => 'Major Courses',
            'color' => '#2196F3',
        ]);

        $this->subType = CourseType::create([
            'id' => 'core-required',
            'name' => 'Core Required',
            'color' => '#FF9800',
        ]);

        // Create test courses
        $this->course1 = Course::create([
            'id' => 'CS101',
            'code' => 'CS101',
            'name' => 'Introduction to Programming',
            'credits' => 3,
        ]);

        $this->course2 = Course::create([
            'id' => 'CS102',
            'code' => 'CS102',
            'name' => 'Data Structures',
            'credits' => 3,
        ]);
    }

    /**
     * Test 1: Verify database constraints exist
     */
    public function test_database_has_proper_foreign_key_and_unique_constraint(): void
    {
        // Check that curriculum_credit_pools has the expected columns
        $this->assertTrue(
            \Schema::hasColumn('curriculum_credit_pools', 'curriculum_id'),
            'curriculum_credit_pools table should have curriculum_id column'
        );

        // Create a pool for curriculum A
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $this->assertNotNull($poolA->id);
        $this->assertEquals($this->curriculumA->id, $poolA->curriculum_id);

        // Verify unique constraint: attempting to create duplicate should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Duplicate Core Pool A',
            'top_level_course_type_id' => $this->coreType->id, // Same course type
            'enabled' => true,
            'order_index' => 1,
        ]);
    }

    /**
     * Test 2: Identical pools can exist in different curricula
     */
    public function test_same_course_type_pool_can_exist_in_different_curricula(): void
    {
        // Create "core" pool in Curriculum A
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool for Curriculum A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Create "core" pool in Curriculum B (same course type, different curriculum)
        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool for Curriculum B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Both should exist independently
        $this->assertNotEquals($poolA->id, $poolB->id);
        $this->assertEquals($this->curriculumA->id, $poolA->curriculum_id);
        $this->assertEquals($this->curriculumB->id, $poolB->curriculum_id);
    }

    /**
     * Test 3: API index returns only pools for the specified curriculum
     */
    public function test_api_index_returns_only_pools_for_specified_curriculum(): void
    {
        // Create pools in both curricula
        $poolA1 = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $poolA2 = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Major Pool A',
            'top_level_course_type_id' => $this->majorType->id,
            'enabled' => true,
            'order_index' => 1,
        ]);

        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Get pools for Curriculum A - should only see A's pools
        $responseA = $this->actingAs($this->chairperson)
            ->getJson("/api/curricula/{$this->curriculumA->id}/credit-pools");

        $responseA->assertStatus(200);
        $poolsA = $responseA->json('pools');
        
        $this->assertCount(2, $poolsA);
        $poolNamesA = collect($poolsA)->pluck('name')->toArray();
        $this->assertContains('Core Pool A', $poolNamesA);
        $this->assertContains('Major Pool A', $poolNamesA);
        $this->assertNotContains('Core Pool B', $poolNamesA);

        // Get pools for Curriculum B - should only see B's pools
        $responseB = $this->actingAs($this->chairperson)
            ->getJson("/api/curricula/{$this->curriculumB->id}/credit-pools");

        $responseB->assertStatus(200);
        $poolsB = $responseB->json('pools');
        
        $this->assertCount(1, $poolsB);
        $this->assertEquals('Core Pool B', $poolsB[0]['name']);
    }

    /**
     * Test 4: Pool creation is scoped to curriculum
     */
    public function test_pool_creation_is_scoped_to_curriculum(): void
    {
        $response = $this->actingAs($this->chairperson)
            ->postJson("/api/curricula/{$this->curriculumA->id}/credit-pools", [
                'name' => 'New Core Pool',
                'topLevelCourseTypeId' => $this->coreType->id,
                'enabled' => true,
            ]);

        $response->assertStatus(201);
        
        // Verify pool was created with correct curriculum_id
        $pool = CurriculumCreditPool::where('name', 'New Core Pool')->first();
        $this->assertNotNull($pool);
        $this->assertEquals($this->curriculumA->id, $pool->curriculum_id);
    }

    /**
     * Test 5: Cascade deletion when curriculum is deleted
     */
    public function test_cascade_deletion_when_curriculum_is_deleted(): void
    {
        // Create pool with sub-categories and course attachments in Curriculum A
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $subCategory = SubCategoryPool::create([
            'pool_id' => $poolA->id,
            'course_type_id' => $this->subType->id,
            'required_credits' => 9,
            'order_index' => 0,
        ]);

        $attachment = PoolCourseAttachment::create([
            'sub_category_pool_id' => $subCategory->id,
            'course_id' => $this->course1->id,
        ]);

        // Create pool in Curriculum B (should not be affected)
        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Store IDs for verification
        $poolAId = $poolA->id;
        $subCategoryId = $subCategory->id;
        $attachmentId = $attachment->id;
        $poolBId = $poolB->id;

        // Delete Curriculum A
        $this->curriculumA->delete();

        // Verify Curriculum A's pool, sub-category, and attachment are deleted
        $this->assertNull(CurriculumCreditPool::find($poolAId));
        $this->assertNull(SubCategoryPool::find($subCategoryId));
        $this->assertNull(PoolCourseAttachment::find($attachmentId));

        // Verify Curriculum B's pool still exists
        $this->assertNotNull(CurriculumCreditPool::find($poolBId));
    }

    /**
     * Test 6: Pool update is scoped to curriculum
     */
    public function test_pool_update_is_scoped_to_curriculum(): void
    {
        // Create pools in both curricula
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Attempt to update Pool B using Curriculum A's endpoint
        $response = $this->actingAs($this->chairperson)
            ->putJson("/api/curricula/{$this->curriculumA->id}/credit-pools/{$poolB->id}", [
                'name' => 'Hacked Pool Name',
            ]);

        // Should return 404 because pool B doesn't belong to curriculum A
        $response->assertStatus(404);

        // Verify Pool B was not modified
        $poolB->refresh();
        $this->assertEquals('Core Pool B', $poolB->name);
    }

    /**
     * Test 7: Pool deletion is scoped to curriculum
     */
    public function test_pool_deletion_is_scoped_to_curriculum(): void
    {
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Attempt to delete Pool B using Curriculum A's endpoint
        $response = $this->actingAs($this->chairperson)
            ->deleteJson("/api/curricula/{$this->curriculumA->id}/credit-pools/{$poolB->id}");

        // Should return 404
        $response->assertStatus(404);

        // Verify Pool B still exists
        $this->assertNotNull(CurriculumCreditPool::find($poolB->id));
    }

    /**
     * Test 8: Verify course attachments are curriculum-independent
     */
    public function test_same_course_can_be_attached_to_pools_in_different_curricula(): void
    {
        // Create pools in both curricula
        $poolA = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Core Pool A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        $poolB = CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Core Pool B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        // Create sub-categories in both pools
        $subCatA = SubCategoryPool::create([
            'pool_id' => $poolA->id,
            'course_type_id' => $this->subType->id,
            'required_credits' => 9,
            'order_index' => 0,
        ]);

        $subCatB = SubCategoryPool::create([
            'pool_id' => $poolB->id,
            'course_type_id' => $this->subType->id,
            'required_credits' => 9,
            'order_index' => 0,
        ]);

        // Attach the SAME course to both pools
        $attachmentA = PoolCourseAttachment::create([
            'sub_category_pool_id' => $subCatA->id,
            'course_id' => $this->course1->id,
        ]);

        $attachmentB = PoolCourseAttachment::create([
            'sub_category_pool_id' => $subCatB->id,
            'course_id' => $this->course1->id,
        ]);

        // Both attachments should exist independently
        $this->assertNotEquals($attachmentA->id, $attachmentB->id);
        $this->assertEquals($this->course1->id, $attachmentA->course_id);
        $this->assertEquals($this->course1->id, $attachmentB->course_id);

        // Deleting attachment in A should not affect attachment in B
        $attachmentA->delete();
        $this->assertNull(PoolCourseAttachment::find($attachmentA->id));
        $this->assertNotNull(PoolCourseAttachment::find($attachmentB->id));
    }

    /**
     * Test 9: Verify controller query filters by curriculum_id
     */
    public function test_controller_queries_filter_by_curriculum_id(): void
    {
        // Create multiple pools across curricula
        CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Pool 1 in A',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumA->id,
            'name' => 'Pool 2 in A',
            'top_level_course_type_id' => $this->majorType->id,
            'enabled' => true,
            'order_index' => 1,
        ]);

        CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Pool 1 in B',
            'top_level_course_type_id' => $this->coreType->id,
            'enabled' => true,
            'order_index' => 0,
        ]);

        CurriculumCreditPool::create([
            'curriculum_id' => $this->curriculumB->id,
            'name' => 'Pool 2 in B',
            'top_level_course_type_id' => $this->majorType->id,
            'enabled' => true,
            'order_index' => 1,
        ]);

        // Verify raw database count
        $this->assertEquals(2, CurriculumCreditPool::where('curriculum_id', $this->curriculumA->id)->count());
        $this->assertEquals(2, CurriculumCreditPool::where('curriculum_id', $this->curriculumB->id)->count());
        $this->assertEquals(4, CurriculumCreditPool::count());

        // Verify API response is properly scoped
        $responseA = $this->actingAs($this->chairperson)
            ->getJson("/api/curricula/{$this->curriculumA->id}/credit-pools");
        
        $responseA->assertStatus(200);
        $this->assertCount(2, $responseA->json('pools'));

        $poolNames = collect($responseA->json('pools'))->pluck('name')->toArray();
        $this->assertContains('Pool 1 in A', $poolNames);
        $this->assertContains('Pool 2 in A', $poolNames);
        $this->assertNotContains('Pool 1 in B', $poolNames);
        $this->assertNotContains('Pool 2 in B', $poolNames);
    }
}
