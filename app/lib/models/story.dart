class Story {
  final String id;
  final String title;
  final String categoryId;
  final String content;

  const Story({
    required this.id,
    required this.title,
    required this.categoryId,
    required this.content,
  });

  factory Story.fromJson(Map<String, dynamic> json) {
    return Story(
      id: json['id'].toString(),
      title: json['title'] ?? '',
      categoryId: json['categoryId'] ?? 'c1',
      content: json['content'] ?? '',
    );
  }
}
