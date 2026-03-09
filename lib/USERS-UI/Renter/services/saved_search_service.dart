// Stub file for SavedSearchService
class SavedSearchService {
  Future<List<Map<String, dynamic>>> getSavedSearches() async {
    return [];
  }

  Future<bool> saveSearch(String name, Map<String, dynamic> filters) async {
    return true;
  }

  Future<void> deleteSearch(String id) async {}
}
